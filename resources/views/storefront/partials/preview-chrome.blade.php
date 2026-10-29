@if($previewModeActive ?? false)
    @if($usingFallbackPayload ?? false)
        <div id="preview-fallback-banner" role="status" style="background-color: #fef3c7; color: #92400e; padding: 0.75rem 1rem; border-bottom: 1px solid #fcd34d; font-size: 0.875rem; text-align: center; font-weight: 500;">Showing last valid saved state — current draft inputs have validation errors.</div>
    @endif
    @if($isUnservable ?? false)
        <div id="preview-unservable-placeholder" role="alert" style="background-color: #fee2e2; color: #991b1b; padding: 2rem 1rem; border: 1px dashed #f87171; border-radius: 0.375rem; font-size: 0.875rem; text-align: center; margin: 1rem auto; max-width: 600px;">Preview unavailable: stored block payload failed schema validation.</div>
    @endif
    <style>
        [data-preview-target="true"] {
            outline: 3px solid #6366f1 !important;
            outline-offset: 4px !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var target = document.getElementById('preview-target-block');
            if (target) {
                target.scrollIntoView({ behavior: 'instant', block: 'center' });
            }
            document.querySelectorAll('a[href]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                });
            });
            document.querySelectorAll('form').forEach(function (el) {
                el.addEventListener('submit', function (e) {
                    e.preventDefault();
                });
            });
        });
    </script>
@endif
