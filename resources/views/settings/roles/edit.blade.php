@extends('layouts.simple')

@section('body')

    <div class="container small">
        @include('settings.parts.navbar', ['selected' => 'roles'])

        <div class="card content-wrap">
            <h1 class="list-heading">{{ trans('settings.role_edit') }}</h1>

            <form action="{{ url("/settings/roles/{$role->id}") }}" method="POST">
                {{ csrf_field() }}
                {{ method_field('PUT') }}

                @include('settings.roles.parts.form', ['role' => $role])

                <div class="form-group text-right">
                    <a href="{{ url("/settings/roles") }}" class="button outline">{{ trans('common.cancel') }}</a>
                    <a href="{{ url("/settings/roles/new?copy_from={$role->id}") }}" class="button outline">{{ trans('common.copy') }}</a>
                    <a href="{{ url("/settings/roles/delete/{$role->id}") }}" class="button outline">{{ trans('settings.role_delete') }}</a>
                    <button type="submit" class="button">{{ trans('settings.role_save') }}</button>
                </div>
            </form>

        </div>


        <div class="card content-wrap auto-height">
            <h2 class="list-heading">{{ trans('settings.role_users') }}</h2>
            <p class="text-small">{{ trans('settings.role_users_desc') }}</p>
            @if(count($role->users ?? []) > 0)
                <div class="item-list">
                    @foreach($role->users as $user)
                        <div class="flex-container-row item-list-row items-center wrap py-xs">
                            <div class="px-m py-xs flex-container-row items-center gap-m min-width-m">
                                <img class="avatar med" width="40" height="40" src="{{ $user->getAvatar(40) }}" alt="{{ $user->name }}">
                                <div>
                                    @if(userCan(\BookStack\Permissions\Permission::UsersManage) || user()->id == $user->id)
                                        <a href="{{ url("/settings/users/{$user->id}") }}">{{ $user->name }}</a>
                                    @else
                                        {{ $user->name }}
                                    @endif
                                    <br>
                                    <span class="text-muted">{{ $user->email }}</span>
                                    @if($user->mfa_values_count > 0)
                                        <span title="{{ trans('settings.users_mfa') }}" class="text-pos">@icon('lock')</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-muted">
                    {{ trans('settings.role_users_none') }}
                </p>
            @endif
        </div>
    </div>

@stop
