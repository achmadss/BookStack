<div class="setting-list">

    <div class="pt-m">
        <label class="setting-list-label">{{ trans('settings.user_group_details') }}</label>
        <p class="small">{{ trans('settings.user_group_details_desc') }}</p>
        <div class="grid half mt-m gap-xl">
            <div>
                <label for="name">{{ trans('settings.user_group_name') }}</label>
                @include('form.text', ['name' => 'name', 'model' => $group])
            </div>
            <div>
                <label for="description">{{ trans('settings.user_group_desc') }}</label>
                @include('form.text', ['name' => 'description', 'model' => $group])
            </div>
        </div>
    </div>

</div>
