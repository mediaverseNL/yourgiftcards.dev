<?php

namespace App\Http\Controllers;

use App\Order;
use App\OrderedProduct;
use App\Product;
use App\Productkey;

use Validator;
use Cart;
use Mail;
use Config;

use Illuminate\Http\Request;

use App\Http\Requests;
use Mollie\Laravel\Facades\Mollie;

class MollieController extends Controller
{
    const STATUS_PENDING = 'pending';
    const STATUS_OPEN = 'open';
    const STATUS_COMPLETED = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';

    protected $mollie;
    protected $order;
    protected $property;

    public function __construct()
    {
        $this->mollie = Mollie::api();
        $this->order = new Order();
        $this->property = new Product();
    }

    public function create(Request $request)
    {
        if (Cart::count() == 0)
            return redirect()->route('cart.index');

        $rules = [
            'fullname' => 'required',
            'email' => 'required|email',
            'methods' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        $price = Config::get('mollie.transaction_costs.'.$request->methods.'.price');
        $percentage =  Config::get('mollie.transaction_costs.'.$request->methods.'.percentage');

        $total = number_format((Cart::subtotal() + $price) * ($percentage + 1), 2);

        $service_cost = [];
        foreach (Cart::content() as $item){
            $service_cost[] = $item->options[0]->servicecosts * $item->qty ;
        }

        $neworder = $this->order;

        $neworder->status = self::STATUS_OPEN;
        $neworder->total = $total;
        $neworder->service_cost = array_sum($service_cost);
        $neworder->transaction_cost = ($total - Cart::subtotal());
        $neworder->method = $request->methods;
        $neworder->fullname = $request->fullname;
        $neworder->email = $request->email;

        $neworder->save();

        $payment =  $this->mollie->payments()->create([
            "amount"      => $total,
            "description" => "Order Nr. ". $neworder->id,
            "redirectUrl" => route('order.get', $neworder->id),
            'metadata'    => array(
                'order_id' => $neworder->id,
            ),
            "method" => $neworder->method,
        ]);

        $order = $this->order->find($neworder->id);

        $order->payment_id = $payment->id;
        $order->status = self::STATUS_PENDING;

        $order->save();

        $data = [];
        foreach (Cart::content() as $item){

            $keys = Productkey::where('product_id', $item->options[0]->id)
                ->where('region', \App::getLocale())
                ->where('status', 'sell')
                ->orderBy('created_at', 'DESC')
                ->limit($item->qty)
                ->get();

            if(!($item->qty <= count($keys))){
                return redirect()->route('cart.index');
            }

            foreach ($keys as $key){
                //update product status
                Productkey::where('id', $key->id)
                    ->where('status', '!=', 'sold')
                    ->update(['status' => 'pending']);

                $data[] = [
                    'order_id' => $order->id,
                    'productkey_id' => $key->id,
                    'price'=> $item->options[0]->price,
                    'servicecosts' => $item->options[0]->servicecosts
                ];
            }
        }

        OrderedProduct::insert($data);

        $array = [];
        foreach ( Cart::content() as $item){
            $array[] = $item->options[0]->servicecosts * $item->qty ;
        }
//
        session()->forget('trash');
        session(['trash' => $payment->id]);

        Mail::send('mail.order', ['order' => Cart::content(), 'payment' => $order, 'subtotal' => Cart::subtotal(), 'servicecost' => array_sum($array)], function($m) use ($request){
            $m->from('info@justgiftcards.com', 'Justgiftcard.nl');
            $m->to($request->email, $request->fullname)->subject(trans('mail.order.subject'));
        });

        Cart::destroy();

        return redirect($payment->getPaymentUrl());
    }

    public function get($id)
    {
        $order = $this->order->find($id);

        $payment =  $this->mollie->payments()->get($order->payment_id);

        if(session('trash') !== $order->payment_id)
            return redirect()->route('cart.index');

        if ($payment->isPaid())
        {
            $order->status = self::STATUS_COMPLETED;

            foreach ($order->orderedProduct as $item){
                Productkey::where('id', '=', $item->productkey_id)
                    ->where('status', '!=', 'sold')
                    ->update(['status' => 'sold']);
            }

            Mail::send('mail.payment', ['order' => $order], function($m) use ($order){
                $m->from('info@justgiftcards.com', 'Justgiftcard.nl');
                $m->to($order->email, $order->fullname)->subject(trans('mail.payment.subject'));
            });
        }
        elseif (!$payment->isOpen())
        {
            $order->status = self::STATUS_CANCELLED;

            foreach ($order->orderedProduct as $item){
                Productkey::where('id', '=', $item->productkey_id)
                    ->where('status', '!=', 'sold')
                    ->update(['status' => 'sell']);
            }
        }

        $order->save();

        return view('order')->with('order', $order)->with('payment', $payment);
    }

}
