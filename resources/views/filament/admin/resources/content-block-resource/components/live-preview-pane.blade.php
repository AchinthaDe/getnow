@php
    $record = $getRecord();
    $mode = in_array($this->previewMode, ['page', 'isolated'], true) ? $this->previewMode : 'page';
    $device = in_array($this->previewDevice, ['desktop', 'tablet', 'mobile'], true) ? $this->previewDevice : 'desktop';
    $viewportWidth = match ($device) {
        'tablet' => '768px',
        'mobile' => '375px',
        default => '100%',
    };
    $previewUrl = route('admin.content-blocks.preview.' . $mode, [
        'contentBlock' => $record->public_id,
        'live' => 1,
        'token' => $this->previewToken,
    ]);
@endphp

<div
    x-data
    x-init="
        let timer = null;
        const form = $el.closest('form');
        if (form) {
            form.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => $wire.syncPreviewState(), 500);
            });
            form.addEventListener('change', () => {
                clearTimeout(timer);
                timer = setTimeout(() => $wire.syncPreviewState(), 250);
            });
        }
    "
    class="space-y-3"
>
    <div class="flex flex-wrap items-center justify-between gap-2 p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-xs">
        <div class="flex items-center gap-1">
            <button
                type="button"
                wire:click="setPreviewMode('page')"
                class="px-2.5 py-1 rounded font-medium transition {{ $mode === 'page' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
            >
                Page Context
            </button>
            <button
                type="button"
                wire:click="setPreviewMode('isolated')"
                class="px-2.5 py-1 rounded font-medium transition {{ $mode === 'isolated' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
            >
                Isolated Block
            </button>
        </div>

        <div class="flex items-center gap-1">
            <button
                type="button"
                wire:click="setPreviewDevice('desktop')"
                class="px-2 py-1 rounded font-medium transition {{ $device === 'desktop' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                title="Desktop (100%)"
            >
                Desktop
            </button>
            <button
                type="button"
                wire:click="setPreviewDevice('tablet')"
                class="px-2 py-1 rounded font-medium transition {{ $device === 'tablet' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                title="Tablet (768px)"
            >
                Tablet
            </button>
            <button
                type="button"
                wire:click="setPreviewDevice('mobile')"
                class="px-2 py-1 rounded font-medium transition {{ $device === 'mobile' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                title="Mobile (375px)"
            >
                Mobile
            </button>
            <button
                type="button"
                wire:click="syncPreviewState"
                class="ml-1 px-2 py-1 rounded font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                title="Refresh Preview"
            >
                Refresh
            </button>
        </div>
    </div>

    <div
        wire:key="preview-frame-{{ $mode }}-{{ $this->previewToken }}"
        class="overflow-x-auto bg-gray-50 dark:bg-gray-900 p-2 rounded-lg border border-gray-200 dark:border-gray-700"
    >
        <iframe
            src="{{ $previewUrl }}"
            sandbox="allow-scripts"
            style="width: {{ $viewportWidth }}; height: 620px; border: 1px solid #e5e7eb; border-radius: 6px; margin: 0 auto; display: block;"
        ></iframe>
    </div>
</div>
