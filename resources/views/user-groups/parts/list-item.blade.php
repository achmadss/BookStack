<div class="item-list-row flex-container-row py-xs items-center">
    <div class="py-xs px-m flex-2">
        <a href="{{ url("/settings/user-groups/{$group->id}") }}">{{ $group->name }}</a>
        <br>
        <small>{{ $group->description }}</small>
    </div>
    <div class="text-right flex py-xs px-m text-muted">
        {{ trans_choice('settings.user_groups_x_users', $group->users_count, ['count' => $group->users_count]) }}
        <br>
        {{ trans_choice('settings.user_groups_x_content', $group->content_count, ['count' => $group->content_count]) }}
    </div>
</div>
