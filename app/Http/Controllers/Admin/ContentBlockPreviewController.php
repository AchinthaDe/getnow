<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Actions\ResolveContentBlockPreview;
use App\Domain\Content\Models\ContentBlock;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\DemoHomeController;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ContentBlockPreviewController extends Controller
{
    public function isolated(
        Request $request,
        ContentBlock $contentBlock,
        ResolveContentBlockPreview $resolvePreview,
    ): Response {
        $user = $request->user();
        $panel = Filament::getPanel('admin');

        abort_unless(
            $user instanceof User
            && $user->canAccessPanel($panel)
            && $user->can('view', $contentBlock),
            403,
        );

        $preview = $resolvePreview(
            $contentBlock,
            (int) $user->getAuthIdentifier(),
            includeFullLayout: false,
            useEphemeralCache: $request->boolean('live'),
        );

        return response()
            ->view('filament.admin.resources.content-block-resource.pages.isolated-preview', [
                'preview' => $preview,
                'block' => $contentBlock,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function page(
        Request $request,
        ContentBlock $contentBlock,
        ResolveContentBlockPreview $resolvePreview,
    ): Response {
        $user = $request->user();
        $panel = Filament::getPanel('admin');

        abort_unless(
            $user instanceof User
            && $user->canAccessPanel($panel)
            && $user->can('view', $contentBlock),
            403,
        );

        $preview = $resolvePreview(
            $contentBlock,
            (int) $user->getAuthIdentifier(),
            includeFullLayout: true,
            useEphemeralCache: $request->boolean('live'),
        );

        return response()
            ->view('storefront.home', [
                'layout' => $preview->layout,
                'featured' => DemoHomeController::getFeaturedProducts(),
                'newProducts' => DemoHomeController::getNewProducts(),
                'categories' => DemoHomeController::getCategories(),
                'cartCount' => 0,
                'previewModeActive' => true,
                'previewTargetId' => $contentBlock->public_id,
                'usingFallbackPayload' => $preview->usingFallbackPayload,
                'isUnservable' => $preview->isUnservable,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
