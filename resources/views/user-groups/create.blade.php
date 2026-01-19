@extends('layouts.simple')

@section('body')

    <div class="container small">
        @include('settings.parts.navbar', ['selected' => 'user-groups'])

        <div class="card content-wrap">
            <h1 class="list-heading">{{ trans('settings.user_group_create') }}</h1>

            <form action="{{ url("/settings/user-groups") }}" method="POST">
                {{ csrf_field() }}

                @include('user-groups.parts.form', ['group' => null])

                <div class="form-group text-right">
                    <a href="{{ url("/settings/user-groups") }}" class="button outline">{{ trans('common.cancel') }}</a>
                    <button type="submit" class="button">{{ trans('settings.user_group_save') }}</button>
                </div>
            </form>

        </div>
    </div>

@stop
