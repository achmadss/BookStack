@extends('layouts.simple')

@section('body')
    <div class="container small">

        @include('settings.parts.navbar', ['selected' => 'user-groups'])

        <div class="card content-wrap auto-height">
            <h1 class="list-heading"> {{ trans('settings.user_group_delete') }}</h1>

            <p>{{ trans('settings.user_group_delete_warning', ['groupName' => $group->name]) }}</p>

            <form action="{{ url("/settings/user-groups/delete/{$group->id}") }}" method="POST">
                {!! csrf_field() !!}
                <input type="hidden" name="_method" value="DELETE">

                @if($group->users->count() > 0)
                    <div class="form-group">
                        <p class="text-muted">
                            {{ trans_choice('settings.user_groups_x_users', $group->users->count(), ['count' => $group->users->count()]) }}
                        </p>
                    </div>
                @endif

                @if($group->shelves->count() > 0 || $group->books->count() > 0)
                    <div class="form-group">
                        <p class="text-muted">
                            {{ trans_choice('settings.user_groups_x_content', $group->shelves->count() + $group->books->count(), ['count' => $group->shelves->count() + $group->books->count()]) }}
                        </p>
                    </div>
                @endif

                <div class="grid half v-center">
                    <div>
                        <p class="text-neg">
                            <strong>{{ trans('settings.user_group_delete_confirm') }}</strong>
                        </p>
                    </div>
                    <div>
                        <div class="form-group text-right">
                            <a href="{{ url("/settings/user-groups/{$group->id}") }}" class="button outline">{{ trans('common.cancel') }}</a>
                            <button type="submit" class="button">{{ trans('common.confirm') }}</button>
                        </div>
                    </div>
                </div>


            </form>
        </div>

    </div>
@stop
