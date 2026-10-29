<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use Illuminate\Support\Facades\Cache;

final class SaveContentBlockPreviewState
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __invoke(int $userId, string $publicId, array $rawPayload, int $sortOrder): string
    {
        $token = bin2hex(random_bytes(8));

        Cache::put(
            self::cacheKey($userId, $publicId),
            [
                'payload' => $rawPayload,
                'sort_order' => $sortOrder,
                'token' => $token,
            ],
            now()->addMinutes(15),
        );

        return $token;
    }

    public static function cacheKey(int $userId, string $publicId): string
    {
        return "content_block_preview:{$userId}:{$publicId}";
    }

    public static function clear(int $userId, string $publicId): void
    {
        Cache::forget(self::cacheKey($userId, $publicId));
    }
}
