<?php

declare(strict_types=1);

use App\Domain\Content\Actions\SaveContentBlockPreviewState;
use Illuminate\Support\Facades\Cache;

it('writes ephemeral preview state to cache with generated token and returns token', function (): void {
    $action = new SaveContentBlockPreviewState;
    $userId = 1;
    $publicId = '01JABCDEF01234567890ABCDEF';
    $rawPayload = ['text' => 'Hello preview', 'tone' => 'info'];
    $sortOrder = 2;

    $token = $action($userId, $publicId, $rawPayload, $sortOrder);

    expect(strlen($token))->toBe(16)
        ->and(ctype_xdigit($token))->toBeTrue();

    $cacheKey = SaveContentBlockPreviewState::cacheKey($userId, $publicId);
    expect(Cache::has($cacheKey))->toBeTrue();

    /** @var array{payload: array<string, mixed>, sort_order: int, token: string} $cached */
    $cached = Cache::get($cacheKey);
    expect($cached['payload'])->toBe($rawPayload)
        ->and($cached['sort_order'])->toBe($sortOrder)
        ->and($cached['token'])->toBe($token);
});

it('isolates ephemeral preview state per user for the same block', function (): void {
    $action = new SaveContentBlockPreviewState;
    $publicId = '01JABCDEF01234567890ABCDEF';
    $userA = 1;
    $userB = 2;

    $action($userA, $publicId, ['text' => 'Draft A'], 10);
    $action($userB, $publicId, ['text' => 'Draft B'], 20);

    $cachedA = Cache::get(SaveContentBlockPreviewState::cacheKey($userA, $publicId));
    $cachedB = Cache::get(SaveContentBlockPreviewState::cacheKey($userB, $publicId));

    expect($cachedA['payload']['text'])->toBe('Draft A')
        ->and($cachedA['sort_order'])->toBe(10)
        ->and($cachedB['payload']['text'])->toBe('Draft B')
        ->and($cachedB['sort_order'])->toBe(20);
});

it('clears ephemeral preview state on clear call', function (): void {
    $action = new SaveContentBlockPreviewState;
    $userId = 1;
    $publicId = '01JABCDEF01234567890ABCDEF';

    $action($userId, $publicId, ['text' => 'To be cleared'], 5);
    expect(Cache::has(SaveContentBlockPreviewState::cacheKey($userId, $publicId)))->toBeTrue();

    SaveContentBlockPreviewState::clear($userId, $publicId);
    expect(Cache::has(SaveContentBlockPreviewState::cacheKey($userId, $publicId)))->toBeFalse();
});
