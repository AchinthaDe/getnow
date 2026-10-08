<?php

declare(strict_types=1);

use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarData;
use Illuminate\Validation\ValidationException;

it('accepts valid data', function () {
    $data = AnnouncementBarData::validateAndCreate([
        'text' => str_repeat('a', 120),
        'tone' => 'info',
    ]);
    expect($data->text)->toBe(str_repeat('a', 120));
});

it('rejects empty text', function () {
    expect(fn () => AnnouncementBarData::validateAndCreate(['text' => '', 'tone' => 'info']))
        ->toThrow(ValidationException::class);
});

it('rejects text longer than 120', function () {
    expect(fn () => AnnouncementBarData::validateAndCreate(['text' => str_repeat('a', 121), 'tone' => 'info']))
        ->toThrow(ValidationException::class);
});

it('rejects HTML in text', function () {
    expect(fn () => AnnouncementBarData::validateAndCreate(['text' => 'Hello <b>World</b>', 'tone' => 'info']))
        ->toThrow(ValidationException::class);

    // Should pass NoHtml
    $data = AnnouncementBarData::validateAndCreate(['text' => 'I <3 this', 'tone' => 'info']);
    expect($data->text)->toBe('I <3 this');
});

it('rejects invalid tone', function () {
    expect(fn () => AnnouncementBarData::validateAndCreate(['text' => 'Valid', 'tone' => 'invalid']))
        ->toThrow(ValidationException::class);
});

it('normalizes empty link_url to null', function () {
    $data = AnnouncementBarData::validateAndCreate([
        'text' => 'Valid',
        'link_url' => '   ',
        'tone' => 'info',
    ]);
    expect($data->link_url)->toBeNull();
});

it('normalizes HTTPS scheme case only', function () {
    $data = AnnouncementBarData::validateAndCreate([
        'text' => 'Valid',
        'link_url' => 'HTTPS://x.com/Path',
        'tone' => 'info',
    ]);
    expect($data->link_url)->toBe('https://x.com/Path');
});
