<?php

declare(strict_types=1);

use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarData;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\Tone;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Filament\Blocks\AnnouncementBarForm;
use App\Filament\Blocks\BlockFormRegistry;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\File;

it('registers form schemas matching all domain block types bidirectionally', function () {
    $domainTypes = array_keys(app(BlockRegistry::class)->all());
    $formTypes = BlockFormRegistry::types();

    expect($formTypes)->toEqualCanonicalizing($domainTypes);
});

it('throws UnknownBlockTypeException for unregistered block type', function () {
    BlockFormRegistry::get('unknown_block_type');
})->throws(UnknownBlockTypeException::class);

it('defines announcement bar schema with 5 fields', function () {
    $schema = AnnouncementBarForm::schema();

    expect($schema)->toHaveCount(5);

    $fields = [];
    foreach ($schema as $component) {
        if ($component instanceof Field) {
            $fields[$component->getName()] = $component;
        }
    }

    expect($fields)->toHaveKeys(['text', 'secondary_text', 'locale_text', 'link_url', 'tone']);

    /** @var TextInput $text */
    $text = $fields['text'];
    /** @var TextInput $secondaryText */
    $secondaryText = $fields['secondary_text'];
    /** @var TextInput $localeText */
    $localeText = $fields['locale_text'];
    /** @var TextInput $linkUrl */
    $linkUrl = $fields['link_url'];
    /** @var Select $tone */
    $tone = $fields['tone'];

    expect($text)->toBeInstanceOf(TextInput::class)
        ->and($text->getName())->toBe('text')
        ->and($text->isRequired())->toBeTrue()
        ->and($text->getMaxLength())->toBe(AnnouncementBarData::TEXT_MAX_LENGTH)
        ->and($secondaryText)->toBeInstanceOf(TextInput::class)
        ->and($secondaryText->getName())->toBe('secondary_text')
        ->and($secondaryText->isRequired())->toBeFalse()
        ->and($secondaryText->getMaxLength())->toBe(AnnouncementBarData::SECONDARY_TEXT_MAX_LENGTH)
        ->and($localeText)->toBeInstanceOf(TextInput::class)
        ->and($localeText->getName())->toBe('locale_text')
        ->and($localeText->isRequired())->toBeFalse()
        ->and($localeText->getMaxLength())->toBe(AnnouncementBarData::LOCALE_TEXT_MAX_LENGTH)
        ->and($linkUrl)->toBeInstanceOf(TextInput::class)
        ->and($linkUrl->getName())->toBe('link_url')
        ->and($linkUrl->isRequired())->toBeFalse()
        ->and($tone)->toBeInstanceOf(Select::class)
        ->and($tone->getName())->toBe('tone')
        ->and($tone->isRequired())->toBeTrue()
        ->and($tone->getOptions())->toEqualCanonicalizing(
            collect(Tone::cases())->mapWithKeys(fn (Tone $t): array => [$t->value => ucfirst($t->value)])->all()
        );
});

it('contains zero forbidden components or actions in filament content block files', function () {
    $forbiddenStrings = [
        'RichEditor',
        'MarkdownEditor',
        'CodeEditor',
        'KeyValue',
        '->url(',
        'DeleteAction',
        'ForceDeleteAction',
        'RestoreAction',
    ];

    $scanPaths = [
        app_path('Filament/Blocks'),
        app_path('Filament/Admin/Resources/ContentBlockResource/Schemas'),
        app_path('Filament/Admin/Resources/ContentBlockResource/Pages'),
    ];

    $phpFiles = [];
    foreach ($scanPaths as $path) {
        if (File::isDirectory($path)) {
            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() === 'php') {
                    $phpFiles[] = $file->getRealPath();
                }
            }
        }
    }

    expect($phpFiles)->not->toBeEmpty();

    foreach ($phpFiles as $filePath) {
        $content = File::get($filePath);
        foreach ($forbiddenStrings as $forbidden) {
            expect(str_contains($content, $forbidden))
                ->toBeFalse("Forbidden string [{$forbidden}] found in [{$filePath}].");
        }
    }
});
