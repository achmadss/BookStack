@extends('layouts.simple')

@section('body')

    <div class="container small">

        @include('settings.parts.navbar', ['selected' => 'user-groups'])

        <div class="card content-wrap auto-height">

            <div class="grid half v-center">
                <h1 class="list-heading">{{ trans('settings.user_groups') }}</h1>

                <div class="text-right">
                    <a href="{{ url("/settings/user-groups/create") }}" class="button outline my-none">{{ trans('settings.user_group_create') }}</a>
                </div>
            </div>

            <p class="text-muted">{{ trans('settings.user_groups_index_desc') }}</p>

            <div class="flex-container-row items-center justify-space-between gap-m mt-m mb-l wrap">
                <div>
                    <div class="block inline mr-xs">
                        <form method="get" action="{{ url("/settings/user-groups") }}">
                            <input type="text"
                                   name="search"
                                   title="{{ trans('common.search') }}"
                                   placeholder="{{ trans('common.search') }}"
                                   value="{{ $listOptions->getSearch() }}">
                        </form>
                    </div>
                </div>
                <div class="justify-flex-end">
                    @include('common.sort', $listOptions->getSortControlData())
                </div>
            </div>

            <div class="item-list">
                @foreach($userGroups as $group)
                    @include('user-groups.parts.list-item', ['group' => $group])
                @endforeach
            </div>

            <div class="mb-m">
                {{ $userGroups->links() }}
            </div>

        </div>
    </div>

@stop
