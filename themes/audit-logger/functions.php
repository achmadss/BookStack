<?php

use BookStack\Facades\Theme;
use BookStack\Theming\ThemeEvents;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Bookshelf;

/**
 * Audit Logger Theme
 *
 * Forwards activity events and page views to an external audit service.
 *
 * Required environment variables:
 * - AUDIT_ENABLED: Set to true to enable audit logging
 * - AUDIT_ENDPOINT: URL of the audit service endpoint
 * - AUDIT_TOKEN: API token for authentication
 */

$auditEnabled = env('AUDIT_ENABLED', false);
$auditEndpoint = env('AUDIT_ENDPOINT', '');

if (!$auditEnabled || empty($auditEndpoint)) {
    return;
}

/**
 * Send event to audit service (non-blocking with short timeout)
 */
function sendAuditEvent(array $payload): void
{
    $endpoint = env('AUDIT_ENDPOINT');
    $token = env('AUDIT_TOKEN');

    try {
        \Illuminate\Support\Facades\Http::timeout(3)
            ->withHeaders(['X-API-Token' => $token])
            ->post($endpoint, $payload);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::warning('Audit event dispatch failed: ' . $e->getMessage());
    }
}

/**
 * Hook: Activity events (all activity types)
 *
 * This hook fires after any activity is logged by BookStack.
 * We forward all events to the audit service.
 */
Theme::listen(ThemeEvents::ACTIVITY_LOGGED, function (string $type, $detail) {
    $mapping = [
        'page_create' => 'page.created',
        'page_update' => 'page.updated',
        'page_delete' => 'page.deleted',
        'page_restore' => 'page.restored',
        'page_move' => 'page.moved',
        'chapter_create' => 'chapter.created',
        'chapter_update' => 'chapter.updated',
        'chapter_delete' => 'chapter.deleted',
        'chapter_move' => 'chapter.moved',
        'book_create' => 'book.created',
        'book_create_from_chapter' => 'book.created_from_chapter',
        'book_update' => 'book.updated',
        'book_delete' => 'book.deleted',
        'book_sort' => 'book.sorted',
        'bookshelf_create' => 'bookshelf.created',
        'bookshelf_create_from_book' => 'bookshelf.created_from_book',
        'bookshelf_update' => 'bookshelf.updated',
        'bookshelf_delete' => 'bookshelf.deleted',
        'commented_on' => 'comment.commented_on',
        'comment_create' => 'comment.created',
        'comment_update' => 'comment.updated',
        'comment_delete' => 'comment.deleted',
        'permissions_update' => 'permissions.updated',
        'revision_restore' => 'revision.restored',
        'revision_delete' => 'revision.deleted',
        'settings_update' => 'settings.updated',
        'maintenance_action_run' => 'maintenance.action_run',
        'recycle_bin_empty' => 'recycle_bin.emptied',
        'recycle_bin_restore' => 'recycle_bin.restored',
        'recycle_bin_destroy' => 'recycle_bin.destroyed',
        'user_create' => 'user.created',
        'user_update' => 'user.updated',
        'user_delete' => 'user.deleted',
        'api_token_create' => 'api_token.created',
        'api_token_update' => 'api_token.updated',
        'api_token_delete' => 'api_token.deleted',
        'role_create' => 'role.created',
        'role_update' => 'role.updated',
        'role_delete' => 'role.deleted',
        'auth_password_reset_request' => 'auth.password_reset_request',
        'auth_password_reset_update' => 'auth.password_reset_update',
        'auth_login' => 'auth.login',
        'auth_register' => 'auth.register',
        'mfa_setup_method' => 'mfa.setup_method',
        'mfa_remove_method' => 'mfa.remove_method',
        'webhook_create' => 'webhook.created',
        'webhook_update' => 'webhook.updated',
        'webhook_delete' => 'webhook.deleted',
        'import_create' => 'import.created',
        'import_run' => 'import.run',
        'import_delete' => 'import.deleted',
        'sort_rule_create' => 'sort_rule.created',
        'sort_rule_update' => 'sort_rule.updated',
        'sort_rule_delete' => 'sort_rule.deleted',
    ];

    if (!isset($mapping[$type])) {
        return;
    }

    $user = user();
    $payload = [
        'event_type' => $mapping[$type],
        'timestamp' => now()->toIso8601String(),
        'user_id' => $user->id,
        'username' => $user->name,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ];

    if ($detail instanceof Page) {
        $payload['page_id'] = $detail->id;
        $payload['page_slug'] = $detail->slug;
        $payload['page_title'] = $detail->name;
        $payload['book_id'] = $detail->book_id;
        $payload['book_title'] = $detail->book?->name;
    } elseif ($detail instanceof Chapter) {
        $payload['chapter_id'] = $detail->id;
        $payload['chapter_slug'] = $detail->slug;
        $payload['chapter_title'] = $detail->name;
        $payload['book_id'] = $detail->book_id;
        $payload['book_title'] = $detail->book?->name;
    } elseif ($detail instanceof Book) {
        $payload['book_id'] = $detail->id;
        $payload['book_slug'] = $detail->slug;
        $payload['book_title'] = $detail->name;
    } elseif ($detail instanceof Bookshelf) {
        $payload['bookshelf_id'] = $detail->id;
        $payload['bookshelf_slug'] = $detail->slug;
        $payload['bookshelf_title'] = $detail->name;
    }

    sendAuditEvent($payload);
});

/**
 * Hook: Entity views (page, chapter, book, bookshelf)
 *
 * This hook fires after each web request is processed.
 * We detect view requests for pages, chapters, books, and bookshelves and forward them to the audit service.
 */
Theme::listen(ThemeEvents::WEB_MIDDLEWARE_AFTER, function ($request, $response) {
    // Only process successful GET requests
    if ($request->method() !== 'GET' || $response->getStatusCode() !== 200) {
        return;
    }

    // Skip if user is not authenticated
    $user = user();
    if ($user->isGuest()) {
        return;
    }

    $path = $request->path();
    $payload = [
        'timestamp' => now()->toIso8601String(),
        'user_id' => $user->id,
        'username' => $user->name,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ];

    // Match page view route pattern: books/{bookSlug}/page/{pageSlug}
    if (preg_match('#^books/([^/]+)/page/([^/]+)$#', $path, $matches)) {
        [, $bookSlug, $pageSlug] = $matches;

        try {
            $queries = app(\BookStack\Entities\Queries\PageQueries::class);
            $page = $queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
            $payload['event_type'] = 'page.viewed';
            $payload['page_id'] = $page->id;
            $payload['page_slug'] = $page->slug;
            $payload['page_title'] = $page->name;
            $payload['book_id'] = $page->book_id;
            $payload['book_title'] = $page->book?->name;
            sendAuditEvent($payload);
        } catch (\Exception $e) {
        }
    }
    // Match chapter view route pattern: books/{bookSlug}/chapter/{chapterSlug}
    elseif (preg_match('#^books/([^/]+)/chapter/([^/]+)$#', $path, $matches)) {
        [, $bookSlug, $chapterSlug] = $matches;

        try {
            $queries = app(\BookStack\Entities\Queries\ChapterQueries::class);
            $chapter = $queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
            $payload['event_type'] = 'chapter.viewed';
            $payload['chapter_id'] = $chapter->id;
            $payload['chapter_slug'] = $chapter->slug;
            $payload['chapter_title'] = $chapter->name;
            $payload['book_id'] = $chapter->book_id;
            $payload['book_title'] = $chapter->book?->name;
            sendAuditEvent($payload);
        } catch (\Exception $e) {
        }
    }
    // Match book view route pattern: books/{bookSlug}
    elseif (preg_match('#^books/([^/]+)$#', $path, $matches)) {
        [, $bookSlug] = $matches;

        try {
            $queries = app(\BookStack\Entities\Queries\BookQueries::class);
            $book = $queries->findVisibleBySlugOrFail($bookSlug);
            $payload['event_type'] = 'book.viewed';
            $payload['book_id'] = $book->id;
            $payload['book_slug'] = $book->slug;
            $payload['book_title'] = $book->name;
            sendAuditEvent($payload);
        } catch (\Exception $e) {
        }
    }
    // Match bookshelf view route pattern: shelves/{shelfSlug}
    elseif (preg_match('#^shelves/([^/]+)$#', $path, $matches)) {
        [, $shelfSlug] = $matches;

        try {
            $queries = app(\BookStack\Entities\Queries\BookshelfQueries::class);
            $shelf = $queries->findVisibleBySlugOrFail($shelfSlug);
            $payload['event_type'] = 'bookshelf.viewed';
            $payload['bookshelf_id'] = $shelf->id;
            $payload['bookshelf_slug'] = $shelf->slug;
            $payload['bookshelf_title'] = $shelf->name;
            sendAuditEvent($payload);
        } catch (\Exception $e) {
        }
    }
});
