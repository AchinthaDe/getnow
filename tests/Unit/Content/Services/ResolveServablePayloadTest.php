<?php

declare(strict_types=1);

namespace Tests\Unit\Content\Services;

use App\Domain\Content\Actions\GetUpgradedBlockPayload;
use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ResolveServablePayloadTest extends TestCase
{
    private function createService(BlockRegistry $registry): ResolveServablePayload
    {
        return new ResolveServablePayload(new GetUpgradedBlockPayload($registry));
    }

    public function test_valid_payload_returns_servable(): void
    {
        $block = new ContentBlock(['id' => 1, 'public_id' => '01J', 'type' => 'test_block', 'schema_version' => 1, 'payload' => []]);

        $mockPayload = Mockery::mock(BlockPayload::class);
        $mockDef = Mockery::mock(BlockDefinition::class);
        $mockDef->shouldReceive('schemaVersion')->andReturn(1);

        $dataClass = new class extends BlockPayload
        {
            /**
             * @param  Arrayable<string, mixed>|array<string, mixed>  $payload
             */
            public static function validateAndCreate(Arrayable|array $payload): static
            {
                return clone app(self::class);
            }
        };

        $mockDef->shouldReceive('dataClass')->andReturn(get_class($dataClass));

        /** @var MockInterface&BlockRegistry $registry */
        $registry = Mockery::mock(BlockRegistry::class);
        $registry->shouldReceive('get')->with('test_block')->andReturn($mockDef);

        $service = $this->createService($registry);
        $result = $service($block);

        $this->assertTrue($result->isServable());
        $this->assertSame(ServingHealthOutcome::Servable, $result->outcome);
    }

    public function test_failure_results_expose_no_payload_or_message(): void
    {
        $block = new ContentBlock(['id' => 1, 'public_id' => '01J', 'type' => 'unknown', 'schema_version' => 1, 'payload' => []]);

        /** @var MockInterface&BlockRegistry $registry */
        $registry = Mockery::mock(BlockRegistry::class);
        $registry->shouldReceive('get')->with('unknown')->andReturn(null);

        $service = $this->createService($registry);
        $result = $service($block);

        $this->assertFalse($result->isServable());
        $this->assertSame(ServingHealthOutcome::UnknownType, $result->outcome);
        $this->assertNull($result->payload);
    }

    public function test_throttling_first_failure_logs_repeat_does_not_different_reason_or_block_does(): void
    {
        $logSpy = Mockery::spy(LogManager::class);
        Log::swap($logSpy);

        $block1 = new ContentBlock(['id' => 1, 'public_id' => 'B1', 'type' => 'unknown', 'schema_version' => 1, 'payload' => []]);
        $block2 = new ContentBlock(['id' => 2, 'public_id' => 'B2', 'type' => 'unknown', 'schema_version' => 1, 'payload' => []]);

        /** @var MockInterface&BlockRegistry $registry */
        $registry = Mockery::mock(BlockRegistry::class);
        $registry->shouldReceive('get')->andReturn(null); // Always unknown

        $service = $this->createService($registry);

        // 1. First failure logs
        $service($block1);
        $logSpy->shouldHaveReceived('warning')->once();

        // 2. Repeat within TTL does not log
        $service($block1);
        $logSpy->shouldHaveReceived('warning')->once(); // Still once

        // 3. Different block does log
        $service($block2);
        $logSpy->shouldHaveReceived('warning')->twice();

        // 4. Different reason logs (we'll mock an unsupported version for B1)
        $mockDef = Mockery::mock(BlockDefinition::class);
        $mockDef->shouldReceive('schemaVersion')->andReturn(1);
        $block1->schema_version = 99; // Unsupported
        /** @var MockInterface&BlockRegistry $registry2 */
        $registry2 = Mockery::mock(BlockRegistry::class);
        $registry2->shouldReceive('get')->andReturn($mockDef);
        $service2 = $this->createService($registry2);

        $service2($block1);
        // It should log again because outcome is different (UnsupportedVersion)
        $logSpy->shouldHaveReceived('warning')->times(3);
    }

    public function test_cache_failure_still_returns_result_and_logs(): void
    {
        $logSpy = Mockery::spy(LogManager::class);
        Log::swap($logSpy);
        Cache::shouldReceive('add')->andThrow(new Exception('Cache is down'));

        $block = new ContentBlock(['id' => 1, 'public_id' => 'B1', 'type' => 'unknown', 'schema_version' => 1, 'payload' => []]);

        /** @var MockInterface&BlockRegistry $registry */
        $registry = Mockery::mock(BlockRegistry::class);
        $registry->shouldReceive('get')->andReturn(null);

        $service = $this->createService($registry);
        $result = $service($block);

        $this->assertSame(ServingHealthOutcome::UnknownType, $result->outcome);
        $logSpy->shouldHaveReceived('warning')->once();
    }

    public function test_upgrader_called_at_most_once_per_resolve(): void
    {
        $block = new ContentBlock(['id' => 1, 'public_id' => '01J', 'type' => 'test_block', 'schema_version' => 1, 'payload' => []]);

        $mockDef = Mockery::mock(BlockDefinition::class);
        $mockDef->shouldReceive('schemaVersion')->andReturn(2);
        $mockDef->shouldReceive('upgradePayload')->once()->andThrow(new Exception('Upgrade boom'));

        /** @var MockInterface&BlockRegistry $registry */
        $registry = Mockery::mock(BlockRegistry::class);
        $registry->shouldReceive('get')->with('test_block')->andReturn($mockDef);

        $service = $this->createService($registry);
        $result = $service($block);

        $this->assertSame(ServingHealthOutcome::UpgradeFailed, $result->outcome);
    }

    public function test_underlying_exception_message_is_not_exposed(): void
    {
        $logSpy = Mockery::spy(LogManager::class);
        Log::swap($logSpy);

        $block = new ContentBlock(['id' => 1, 'public_id' => 'SEC1', 'type' => 'test_block', 'schema_version' => 1, 'payload' => []]);

        $mockDef = Mockery::mock(BlockDefinition::class);
        $mockDef->shouldReceive('schemaVersion')->andReturn(2);
        $mockDef->shouldReceive('upgradePayload')->once()->andThrow(new Exception('SECRET-MSG'));

        /** @var MockInterface&BlockRegistry $registry */
        $registry = Mockery::mock(BlockRegistry::class);
        $registry->shouldReceive('get')->with('test_block')->andReturn($mockDef);

        $service = $this->createService($registry);
        $result = $service($block);

        $this->assertSame(ServingHealthOutcome::UpgradeFailed, $result->outcome);
        $this->assertStringNotContainsString('SECRET-MSG', json_encode($result));

        $logSpy->shouldHaveReceived('warning')->withArgs(function ($msg, $context) {
            return ! in_array('SECRET-MSG', $context, true) && strpos(json_encode($context), 'SECRET-MSG') === false;
        });
    }

    public function test_log_false_never_logs(): void
    {
        $logSpy = Mockery::spy(LogManager::class);
        Log::swap($logSpy);

        $block = new ContentBlock(['id' => 1, 'public_id' => '01J', 'type' => 'unknown', 'schema_version' => 1, 'payload' => []]);

        /** @var MockInterface&BlockRegistry $registry */
        $registry = Mockery::mock(BlockRegistry::class);
        $registry->shouldReceive('get')->with('unknown')->andReturn(null);

        $service = $this->createService($registry);
        $result = $service($block, false);

        $this->assertSame(ServingHealthOutcome::UnknownType, $result->outcome);
        $logSpy->shouldNotHaveReceived('warning');
    }
}
