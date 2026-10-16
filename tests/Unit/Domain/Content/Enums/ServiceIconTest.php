<?php

declare(strict_types=1);

use App\Domain\Content\Enums\ServiceIcon;

it('verifies every ServiceIcon case exists in components/icon.blade.php', function () {
    $contents = (string) file_get_contents(resource_path('views/components/icon.blade.php'));
    preg_match_all("/'([a-z-]+)'\s*=>/i", $contents, $matches);
    $availableIcons = $matches[1];

    foreach (ServiceIcon::cases() as $case) {
        expect(in_array($case->value, $availableIcons, true))->toBeTrue(
            "Expected ServiceIcon case '{$case->value}' to exist in resources/views/components/icon.blade.php"
        );
    }
});
