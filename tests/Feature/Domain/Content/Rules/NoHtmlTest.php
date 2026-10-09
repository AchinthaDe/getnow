<?php

declare(strict_types=1);

use App\Support\Rules\NoHtml;
use Illuminate\Support\Facades\Validator;

function validateNoHtml(string $text): bool
{
    return Validator::make(['text' => $text], ['text' => [new NoHtml]])->passes();
}

it('rejects html tags', function (string $value) {
    expect(validateNoHtml($value))->toBeFalse();
})->with([
    '<b>',
    '</b>',
    '<!--',
]);

it('accepts non-html angle brackets', function () {
    expect(validateNoHtml('I <3 this'))->toBeTrue();
});
