<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Domain\Content\Actions\GetLayoutContent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class GetLayoutContentController extends Controller
{
    public function __invoke(Request $request, GetLayoutContent $getLayoutContent): Response
    {
        $layout = $getLayoutContent();
        $response = response()->json($layout->toArray(), 200);
        $response->setEtag($layout->meta->revision);

        $expectedEtag = '"'.$layout->meta->revision.'"';
        foreach ($request->getEtags() as $etag) {
            $stripped = (string) preg_replace('/^W\//', '', $etag);
            if ($stripped === '*' || $stripped === $expectedEtag) {
                $response->setNotModified();
                break;
            }
        }

        return $response;
    }
}
