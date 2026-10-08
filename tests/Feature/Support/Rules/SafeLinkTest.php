<?php

declare(strict_types=1);

use App\Support\Rules\SafeLink;
use Illuminate\Support\Facades\Validator;

function validateLink(string $link): bool
{
    $validator = Validator::make(['link' => $link], ['link' => new SafeLink]);

    return $validator->passes();
}

it('accepts valid paths and urls', function () {
    expect(validateLink('/about'))->toBeTrue()
        ->and(validateLink('https://example.com'))->toBeTrue()
        ->and(validateLink('HTTPS://x.com'))->toBeTrue(); // Accepted by rule; normalization happens in Data class
});

it('rejects http, javascript, data', function () {
    expect(validateLink('http://example.com'))->toBeFalse()
        ->and(validateLink('javascript:alert(1)'))->toBeFalse()
        ->and(validateLink('data:text/html,<html>'))->toBeFalse();
});

it('rejects leading slashes that imply protocol relative', function () {
    expect(validateLink('//evil.com'))->toBeFalse()
        ->and(validateLink('/\\evil.com'))->toBeFalse();
});

it('rejects whitespace', function () {
    expect(validateLink('https://example.com/ foo'))->toBeFalse()
        ->and(validateLink("https://example.com/\nfoo"))->toBeFalse();
});

it('rejects empty hosts', function () {
    expect(validateLink('https:///x'))->toBeFalse();
});

it('rejects userinfo', function () {
    expect(validateLink('https://user:pass@evil.com'))->toBeFalse()
        ->and(validateLink('https://x@evil.com'))->toBeFalse();
});
