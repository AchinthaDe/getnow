<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ContentBlockFactory extends Factory
{
    protected $model = ContentBlock::class;

    public function definition(): array
    {
        return [
            'placement' => Placement::ANNOUNCEMENT_BAR->value,
            'type' => 'announcement_bar',
            'schema_version' => 1,
            'payload' => [
                'text' => $this->faker->sentence(),
                'link_url' => null,
                'tone' => 'info',
            ],
            'status' => PublishStatus::DRAFT->value,
            'is_enabled' => true,
            'sort_order' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function draft(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublishStatus::DRAFT->value,
        ]);
    }

    public function published(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublishStatus::PUBLISHED->value,
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublishStatus::ARCHIVED->value,
        ]);
    }

    public function enabled(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => true,
        ]);
    }

    public function disabled(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    public function scheduled(\DateTimeInterface $startsAt, ?\DateTimeInterface $endsAt = null): self
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    public function unknownType(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'unknown_type_for_testing',
        ]);
    }

    public function unsupportedVersion(): self
    {
        return $this->state(fn (array $attributes) => [
            'schema_version' => 9999,
        ]);
    }

    public function failedUpgrade(): self
    {
        if (app(BlockRegistry::class)->get('test_failed_upgrade_block') === null) {
            throw new \LogicException('The test_failed_upgrade_block definition is not registered. Call \Tests\Support\TestBlocks::register() in your test setup.');
        }

        return $this->state(fn (array $attributes) => [
            'type' => 'test_failed_upgrade_block',
            'schema_version' => 1,
            'payload' => ['trigger_upgrade_failure' => true],
        ]);
    }

    public function invalidAfterUpgrade(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'announcement_bar',
            'schema_version' => 1,
            'payload' => ['text' => null], // invalid
        ]);
    }
}
