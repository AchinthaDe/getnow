@php
    $record = $getRecord();
    $pageLabel = $record?->placement?->page()->label() ?? '—';
    $placementLabel = $record?->placement?->label() ?? '—';
    $statusLabel = $record?->status ? ucfirst($record->status->value) : '—';
    $servingResult = $record ? app(\App\Domain\Content\Actions\GetBlockServingStatus::class)($record) : null;
    $servingLabel = $servingResult ? $servingResult->status->label() : '—';
@endphp
<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
            Page: {{ $pageLabel }}
        </span>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
            Placement: {{ $placementLabel }}
        </span>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
            Status: {{ $statusLabel }}
        </span>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
            Serving Status: {{ $servingLabel }}
        </span>
    </div>
    <iframe src="{{ route('admin.content-blocks.preview.isolated', ['contentBlock' => $record->public_id]) }}" sandbox="allow-scripts" style="width: 100%; height: 320px; border: 1px solid #e5e7eb; border-radius: 6px;"></iframe>
</div>
