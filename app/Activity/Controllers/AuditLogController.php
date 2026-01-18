<?php

namespace BookStack\Activity\Controllers;

use BookStack\Activity\ActivityType;
use BookStack\Activity\Models\Activity;
use BookStack\Http\Controller;
use BookStack\Http\DownloadResponseFactory;
use BookStack\Permissions\Permission;
use BookStack\Sorting\SortUrl;
use BookStack\Util\SimpleListOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Collection;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->checkPermission(Permission::SettingsManage);
        $this->checkPermission(Permission::UsersManage);

        $sort = $request->get('sort', 'activity_date');
        $order = $request->get('order', 'desc');
        $listOptions = (new SimpleListOptions('', $sort, $order))->withSortOptions([
            'created_at' => trans('settings.audit_table_date'),
            'type' => trans('settings.audit_table_event'),
        ]);

        $filters = [
            'event'     => $request->get('event', ''),
            'date_from' => $request->get('date_from', ''),
            'date_to'   => $request->get('date_to', ''),
            'user'      => $request->get('user', ''),
            'ip'        => $request->get('ip', ''),
        ];

        $query = Activity::query()
            ->with([
                'loggable' => fn ($query) => $query->withTrashed(),
                'user',
            ])
            ->orderBy($listOptions->getSort(), $listOptions->getOrder());

        if ($filters['event']) {
            $query->where('type', '=', $filters['event']);
        }
        if ($filters['user']) {
            $query->where('user_id', '=', $filters['user']);
        }

        if ($filters['date_from']) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        if ($filters['ip']) {
            $query->where('ip', 'like', $filters['ip'] . '%');
        }

        $activities = $query->paginate(100);
        $activities->appends($request->all());

        $types = ActivityType::all();
        $this->setPageTitle(trans('settings.audit'));

        return view('settings.audit', [
            'activities'    => $activities,
            'filters'       => $filters,
            'listOptions'   => $listOptions,
            'activityTypes' => $types,
            'filterSortUrl' => new SortUrl('settings/audit', array_filter($request->except('page')))
        ]);
    }

    public function exportCsv(Request $request, DownloadResponseFactory $download): Response
    {
        $this->checkPermission(Permission::SettingsManage);
        $this->checkPermission(Permission::UsersManage);

        $query = $this->buildExportQuery($request);
        $activities = $query->get();

        $csv = $this->generateCsvData($activities);

        $filename = 'audit-log-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return $download->directly($csv, $filename);
    }

    protected function buildExportQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $listOptions = (new SimpleListOptions('', 'created_at', 'desc'))->withSortOptions([
            'created_at' => trans('settings.audit_table_date'),
            'type' => trans('settings.audit_table_event'),
        ]);

        $filters = [
            'event'     => $request->get('event', ''),
            'date_from' => $request->get('date_from', ''),
            'date_to'   => $request->get('date_to', ''),
            'user'      => $request->get('user', ''),
            'ip'        => $request->get('ip', ''),
        ];

        $query = Activity::query()
            ->with([
                'loggable' => fn ($query) => $query->withTrashed(),
                'user',
            ])
            ->orderBy($listOptions->getSort(), $listOptions->getOrder());

        if ($filters['event']) {
            $query->where('type', '=', $filters['event']);
        }
        if ($filters['user']) {
            $query->where('user_id', '=', $filters['user']);
        }
        if ($filters['date_from']) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        if ($filters['ip']) {
            $query->where('ip', 'like', $filters['ip'] . '%');
        }

        return $query;
    }

    protected function generateCsvData(Collection $activities): string
    {
        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            trans('settings.audit_date'),
            trans('settings.audit_table_employee_id'),
            trans('settings.audit_user'),
            trans('settings.audit_event'),
            trans('settings.audit_detail'),
            trans('settings.audit_ip'),
            trans('settings.audit_subject'),
        ]);

        foreach ($activities as $activity) {
            $subject = $this->getActivitySubject($activity);
            $employeeId = $activity->user?->employee_id ?? '';
            $detail = $activity->detail ?? '';
            $detail = preg_replace('/^\(\d+\)\s*/', '', $detail);

            fputcsv($output, [
                $activity->created_at?->toIso8601String() ?? '',
                $employeeId !== '' ? "=\"{$employeeId}\"" : '',
                $activity->user?->name ?? trans('common.unknown'),
                $activity->getText(),
                $detail,
                $activity->ip ?? '',
                $subject,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    protected function getActivitySubject(Activity $activity): string
    {
        if (!$activity->loggable) {
            if (empty($activity->loggable_type)) {
                return '';
            }
            return trans('settings.audit_csv_deleted_item');
        }

        $name = $activity->loggable->name ?? $activity->loggable->title ?? trans('common.unknown');
        $type = class_basename($activity->loggable_type);

        return "{$type}: {$name}";
    }
}
