<div dir="auto" class="{{ ($isProtected ?? false) ? 'protected-content' : '' }}">

    <h1 class="break-text {{ ($isProtected ?? false) ? 'protected-content' : '' }}" id="bkmrk-page-title">{{$page->name}}</h1>

    <div style="clear:left;"></div>

    @if (isset($diff) && $diff)
        {!! $diff !!}
    @else
        <div class="{{ ($isProtected ?? false) ? 'protected-content' : '' }}">
            {!! isset($page->renderedHTML) ? $page->renderedHTML : $page->html !!}
        </div>
    @endif
</div>