@extends('layouts.simple')

@section('body')

    <div class="container small">
        @include('settings.parts.navbar', ['selected' => 'user-groups'])

        <div class="card content-wrap auto-height">
            <h1 class="list-heading">{{ trans('settings.user_group_edit') }}</h1>

            <form action="{{ url("/settings/user-groups/{$group->id}") }}" method="POST">
                {{ csrf_field() }}
                {{ method_field('PUT') }}

                @include('user-groups.parts.form', ['group' => $group])

                <div class="form-group text-right">
                    <a href="{{ url("/settings/user-groups") }}" class="button outline">{{ trans('common.cancel') }}</a>
                    <a href="{{ url("/settings/user-groups/delete/{$group->id}") }}" class="button outline">{{ trans('settings.user_group_delete') }}</a>
                    <button type="submit" class="button">{{ trans('settings.user_group_save') }}</button>
                </div>
            </form>

        </div>

        <div class="card content-wrap auto-height">
            <h2 class="list-heading">{{ trans('settings.user_group_members') }}</h2>
            <p class="text-muted">{{ trans('settings.user_group_members_desc') }}</p>

            <form action="{{ url("/settings/user-groups/{$group->id}/membership") }}" method="POST">
                {{ csrf_field() }}
                {{ method_field('PUT') }}

                <div class="mb-l">
                    <label class="setting-list-label">{{ trans('settings.users') }}</label>
                    
                    <!-- Search box with filter support -->
                    <input type="text" 
                           id="search-users"
                           style="width: 100%;"
                           placeholder="Search or filter (role:RoleName)...">
                    <p class="text-muted text-small mt-xs mb-s">
                        {!! trans('settings.user_group_filter_by_role_tip') !!}
                    </p>
                    
                    <!-- Toggle All checkbox -->
                    <div class="mb-s">
                        <label class="checkbox">
                            <input type="checkbox" id="select-all-users">
                            {{ trans('common.toggle_all') }}
                        </label>
                    </div>
                    
                    <!-- Individual checkboxes -->
                    <div id="users-list" class="item-list">
                        @php
                            $allUsers = $availableUsers->merge($group->users)->unique('id')->sortBy('name');
                        @endphp
                        @foreach($allUsers as $user)
                            <label class="item-list-row user-group-item" data-roles="{{ $user->roles->pluck('display_name')->join(',') }}">
                                <div class="flex-container-row items-center gap-m py-xs px-m">
                                    <input type="checkbox" 
                                           name="user_ids[]" 
                                           value="{{ $user->id }}"
                                           {{ $group->users->contains($user->id) ? 'checked' : '' }}>
                                    <img class="avatar small" width="30" height="30" src="{{ $user->getAvatar(30)}}" alt="{{ $user->name }}">
                                    <div class="flex">
                                        <div>{{ $user->name }}</div>
                                        <div class="text-small text-muted">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    
                    <!-- Empty state message -->
                    <p id="users-empty-state" class="text-muted text-center py-m" style="display: none;">
                        {{ trans('common.no_items') }}
                    </p>
                </div>

                <div class="form-group text-right">
                    <button type="submit" class="button">{{ trans('settings.user_group_save_changes') }}</button>
                </div>

            </form>
        </div>

        <div class="card content-wrap auto-height">
            <h2 class="list-heading">{{ trans('settings.user_group_content') }}</h2>
            <p class="text-muted">{{ trans('settings.user_group_content_desc') }}</p>

            <form action="{{ url("/settings/user-groups/{$group->id}/membership") }}" method="POST">
                {{ csrf_field() }}
                {{ method_field('PUT') }}

                <div class="mb-l">
                    <label class="setting-list-label">{{ trans('entities.shelves') }} & {{ trans('entities.books') }}</label>
                    
                    <!-- Search box with filter support -->
                    <input type="text" 
                           id="search-content"
                           style="width: 100%;"
                           placeholder="Search or filter (type:shelf, type:book)...">
                    <p class="text-muted text-small mt-xs mb-s">
                        {!! trans('settings.user_group_filter_by_type_tip') !!}
                    </p>
                    
                    <!-- Toggle All checkbox -->
                    <div class="mb-s">
                        <label class="checkbox">
                            <input type="checkbox" id="select-all-content">
                            {{ trans('common.toggle_all') }}
                        </label>
                    </div>
                    
                    <!-- Combined shelves and books list -->
                    <div id="content-list" class="item-list">
                        @php
                            $allShelves = $availableShelves->merge($group->shelves)->unique('id')->sortBy('name');
                            $allBooks = $availableBooks->merge($group->books)->unique('id')->sortBy('name');
                            
                            // Create combined collection with type info
                            $allContent = collect();
                            foreach ($allShelves as $shelf) {
                                $allContent->push([
                                    'type' => 'shelf',
                                    'id' => $shelf->id,
                                    'name' => $shelf->name,
                                    'assigned' => $group->shelves->contains($shelf->id)
                                ]);
                            }
                            foreach ($allBooks as $book) {
                                $allContent->push([
                                    'type' => 'book',
                                    'id' => $book->id,
                                    'name' => $book->name,
                                    'assigned' => $group->books->contains($book->id)
                                ]);
                            }
                            $allContent = $allContent->sortBy('name');
                        @endphp
                        
                            @foreach($allContent as $item)
                                <label class="item-list-row user-group-item" data-type="{{ $item['type'] }}">
                                    <div class="flex-container-row items-center gap-m py-xs px-m">
                                        <input type="checkbox" 
                                               name="{{ $item['type'] === 'shelf' ? 'shelf_ids[]' : 'book_ids[]' }}" 
                                               value="{{ $item['id'] }}"
                                               {{ $item['assigned'] ? 'checked' : '' }}>
                                        <div class="entity-icon-badge">
                                            @if($item['type'] === 'shelf')
                                                @icon('bookshelf')
                                            @else
                                                @icon('book')
                                            @endif
                                        </div>
                                        <div class="flex">
                                            <div>{{ $item['name'] }}</div>
                                            <div class="text-small text-muted">
                                                {{ $item['type'] === 'shelf' ? trans('entities.shelf') : trans('entities.book') }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                    </div>
                    
                    <!-- Empty state message -->
                    <p id="content-empty-state" class="text-muted text-center py-m" style="display: none;">
                        {{ trans('common.no_items') }}
                    </p>
                </div>

                <div class="form-group text-right">
                    <button type="submit" class="button">{{ trans('settings.user_group_save_changes') }}</button>
                </div>

            </form>
        </div>

    </div>

    <script nonce="{{ $cspNonce }}">
        /**
         * Simple user group management with checkboxes
         * Handles search, toggle all, role filtering (role:RoleName), and type filtering (type:shelf, type:book)
         */
        (function() {
            // Debounce helper function
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
            
            // Parse user search query to extract role filters and search terms
            function parseUserSearchQuery(query) {
                const result = {
                    roleFilter: null, // null = show all, or specific role name
                    searchTerms: ''
                };
                
                // Match role:RoleName (case insensitive)
                const roleMatch = query.match(/role:([^\s]+)/i);
                if (roleMatch) {
                    result.roleFilter = roleMatch[1].toLowerCase();
                    // Remove the role filter from the search query
                    result.searchTerms = query.replace(/role:[^\s]+/gi, '').trim();
                } else {
                    result.searchTerms = query.trim();
                }
                
                return result;
            }
            
            function init() {
                // User search
                const searchUsers = document.getElementById('search-users');
                const usersList = document.getElementById('users-list');
                const usersEmptyState = document.getElementById('users-empty-state');
                const selectAllUsers = document.getElementById('select-all-users');

                // Apply filters to user list
                function applyUserFilters(query) {
                    if (!usersList) {
                        console.log('usersList not found!');
                        return;
                    }
                    
                    const parsed = parseUserSearchQuery(query);
                    const searchLower = parsed.searchTerms.toLowerCase();
                    
                    console.log('User Query:', query);
                    console.log('User Parsed:', parsed);
                    
                    const labels = usersList.querySelectorAll('label.item-list-row');
                    console.log('Found user labels:', labels.length);
                    
                    let lastVisibleLabel = null;
                    let visibleCount = 0;
                    
                    labels.forEach(label => {
                        const roles = label.getAttribute('data-roles') || '';
                        const rolesLower = roles.toLowerCase();
                        const text = label.textContent.toLowerCase();
                        
                        // Remove last-visible class from all
                        label.classList.remove('last-visible');
                        
                        // Check role filter
                        let roleVisible = true;
                        if (parsed.roleFilter !== null) {
                            // Check if any of the user's roles match the filter
                            roleVisible = rolesLower.split(',').some(role => 
                                role.trim().includes(parsed.roleFilter)
                            );
                        }
                        
                        // Check search filter
                        const searchVisible = searchLower === '' || text.includes(searchLower);
                        
                        // Show only if both filters pass
                        const shouldShow = roleVisible && searchVisible;
                        label.style.display = shouldShow ? '' : 'none';
                        
                        if (shouldShow) {
                            lastVisibleLabel = label;
                            visibleCount++;
                        }
                        
                        if (labels.length <= 5) { // Only log details for small lists
                            console.log('User:', text.substring(0, 30), 'roles:', roles, 'roleVisible:', roleVisible, 'searchVisible:', searchVisible, 'show:', shouldShow);
                        }
                    });
                    
                    // Add last-visible class to the last visible item
                    if (lastVisibleLabel) {
                        lastVisibleLabel.classList.add('last-visible');
                    }
                    
                    // Show/hide empty state
                    if (usersEmptyState) {
                        if (visibleCount === 0) {
                            usersList.style.display = 'none';
                            usersEmptyState.style.display = 'block';
                        } else {
                            usersList.style.display = '';
                            usersEmptyState.style.display = 'none';
                        }
                    }
                    
                    console.log('Visible users:', visibleCount);
                }

                if (searchUsers && usersList) {
                    // Prevent form submission on Enter key
                    searchUsers.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            return false;
                        }
                    });
                    
                    // Debounced search function
                    const performUserSearch = debounce(function(query) {
                        applyUserFilters(query);
                    }, 300); // 300ms delay
                    
                    searchUsers.addEventListener('input', function() {
                        const query = this.value.toLowerCase();
                        performUserSearch(query);
                    });
                    
                    // Initialize last-visible on page load
                    const labels = usersList.querySelectorAll('label.item-list-row');
                    if (labels.length > 0) {
                        const lastLabel = labels[labels.length - 1];
                        lastLabel.classList.add('last-visible');
                    }
                }

                if (selectAllUsers && usersList) {
                    selectAllUsers.addEventListener('change', function() {
                        const checkboxes = usersList.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(cb => {
                            const label = cb.closest('label.item-list-row');
                            if (label && label.style.display !== 'none') {
                                cb.checked = this.checked;
                            }
                        });
                    });
                }

                // Content (shelves and books) filtering
                const searchContent = document.getElementById('search-content');
                const contentList = document.getElementById('content-list');
                const contentEmptyState = document.getElementById('content-empty-state');
                const selectAllContent = document.getElementById('select-all-content');

                // Parse search query to extract filters and search terms
                function parseSearchQuery(query) {
                    const result = {
                        typeFilter: null, // null = show all, 'shelf' = show only shelves, 'book' = show only books
                        searchTerms: ''
                    };
                    
                    // Match type:shelf or type:book (case insensitive)
                    const typeMatch = query.match(/type:(shelf|book)/i);
                    if (typeMatch) {
                        result.typeFilter = typeMatch[1].toLowerCase();
                        // Remove the type filter from the search query
                        result.searchTerms = query.replace(/type:(shelf|book)/gi, '').trim();
                    } else {
                        result.searchTerms = query.trim();
                    }
                    
                    return result;
                }

                // Apply filters to content list
                function applyContentFilters(query) {
                    if (!contentList) {
                        console.log('contentList not found!');
                        return;
                    }
                    
                    const parsed = parseSearchQuery(query);
                    const searchLower = parsed.searchTerms.toLowerCase();
                    
                    console.log('Query:', query);
                    console.log('Parsed:', parsed);
                    
                    const labels = contentList.querySelectorAll('label.item-list-row');
                    console.log('Found labels:', labels.length);
                    
                    let lastVisibleLabel = null;
                    let visibleCount = 0;
                    
                    labels.forEach(label => {
                        const type = label.getAttribute('data-type');
                        const text = label.textContent.toLowerCase();
                        
                        // Remove last-visible class from all
                        label.classList.remove('last-visible');
                        
                        // Check type filter
                        let typeVisible = true;
                        if (parsed.typeFilter !== null) {
                            typeVisible = (type === parsed.typeFilter);
                        }
                        
                        // Check search filter
                        const searchVisible = searchLower === '' || text.includes(searchLower);
                        
                        // Show only if both filters pass
                        const shouldShow = typeVisible && searchVisible;
                        label.style.display = shouldShow ? '' : 'none';
                        
                        if (shouldShow) {
                            lastVisibleLabel = label;
                            visibleCount++;
                        }
                        
                        if (labels.length <= 5) { // Only log details for small lists
                            console.log('Item:', type, text.substring(0, 30), 'typeVisible:', typeVisible, 'searchVisible:', searchVisible, 'show:', shouldShow);
                        }
                    });
                    
                    // Add last-visible class to the last visible item
                    if (lastVisibleLabel) {
                        lastVisibleLabel.classList.add('last-visible');
                    }
                    
                    // Show/hide empty state
                    if (contentEmptyState) {
                        if (visibleCount === 0) {
                            contentList.style.display = 'none';
                            contentEmptyState.style.display = 'block';
                        } else {
                            contentList.style.display = '';
                            contentEmptyState.style.display = 'none';
                        }
                    }
                }

                // Search handler with debounce
                if (searchContent) {
                    // Prevent form submission on Enter key
                    searchContent.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            return false;
                        }
                    });
                    
                    // Debounced search
                    const performContentSearch = debounce(function(query) {
                        applyContentFilters(query);
                    }, 300); // 300ms delay
                    
                    searchContent.addEventListener('input', function() {
                        performContentSearch(this.value);
                    });
                    
                    // Initialize last-visible on page load
                    const labels = contentList.querySelectorAll('label.item-list-row');
                    if (labels.length > 0) {
                        const lastLabel = labels[labels.length - 1];
                        lastLabel.classList.add('last-visible');
                    }
                }

                // Toggle all content
                if (selectAllContent && contentList) {
                    selectAllContent.addEventListener('change', function() {
                        const checkboxes = contentList.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(cb => {
                            const label = cb.closest('label.item-list-row');
                            if (label && label.style.display !== 'none') {
                                cb.checked = this.checked;
                            }
                        });
                    });
                }
            }

            // Initialize on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>

@stop
