<?php

use BookStack\Facades\Theme;
use BookStack\Theming\ThemeEvents;
use BookStack\Entities\Models\Page;

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
 * Hook: Activity events (create/update/delete)
 *
 * This hook fires after any activity is logged by BookStack.
 * We filter for page-related events and forward them to the audit service.
 */
Theme::listen(ThemeEvents::ACTIVITY_LOGGED, function (string $type, $detail) {
    $mapping = [
        'page_create' => 'page.created',
        'page_update' => 'page.updated',
        'page_delete' => 'page.deleted',
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
    }

    sendAuditEvent($payload);
});

/**
 * Hook: Page views
 *
 * This hook fires after each web request is processed.
 * We detect page view requests and forward them to the audit service.
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

    // Match page view route pattern: books/{bookSlug}/page/{pageSlug}
    $path = $request->path();
    if (!preg_match('#^books/([^/]+)/page/([^/]+)$#', $path, $matches)) {
        return;
    }

    [, $bookSlug, $pageSlug] = $matches;

    // Resolve the page to get full details
    try {
        $queries = app(\BookStack\Entities\Queries\PageQueries::class);
        $page = $queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
    } catch (\Exception $e) {
        return;
    }

    sendAuditEvent([
        'event_type' => 'page.viewed',
        'timestamp' => now()->toIso8601String(),
        'user_id' => $user->id,
        'username' => $user->name,
        'page_id' => $page->id,
        'page_slug' => $page->slug,
        'page_title' => $page->name,
        'book_id' => $page->book_id,
        'book_title' => $page->book?->name,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);
});
