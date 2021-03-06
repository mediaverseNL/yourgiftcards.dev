
<style>
    ul > .active{
        background-color: #eee;
    }
</style>

<div class="col-lg-2">
    <nav style="background-color: #555">
        <ul class="nav">
            <li class="{{ Request::is('admin') ? 'active' : null }}"><a href="{{route('admin')}}">dashboard</a></li>
            <li class="{{ Request::is('admin/category*') ? 'active' : null }}"><a href="{{route('admin.category')}}">categories</a></li>
            <li class="{{ Request::is('admin/giftcard*') ? 'active' : null }}"><a href="{{route('admin.product')}}">giftcards</a></li>
            <li class="{{ Request::is('admin/stock*') ? 'active' : null }}"><a href="{{route('admin.stock')}}">stock</a></li>
            <li class="{{ Request::is('admin/order*') ? 'active' : null }}"><a href="{{route('admin.order')}}">order</a></li>
            <li class="{{ Request::is('admin/blog*') ? 'active' : null }}"><a href="{{route('admin.blog')}}">nieuws</a></li>
            <li class=""><a href="{{route('admin.translations', '')}}">translation</a></li>
            {{--<li><a href="#">nieuwsbrief</a></li>--}}
            {{--<li><a href="#" id="btn-1" data-toggle="collapse" data-target="#submenu1" aria-expanded="false">Link 2 (toggle)</a>--}}
                {{--<ul class="nav collapse" id="submenu1" role="menu" aria-labelledby="btn-1">--}}
                    {{--<li><a href="#">Link 2.1</a></li>--}}
                    {{--<li><a href="#">Link 2.2</a></li>--}}
                    {{--<li><a href="#">Link 2.3</a></li>--}}
                {{--</ul>--}}
            {{--</li>--}}
        </ul>
    </nav>
</div>