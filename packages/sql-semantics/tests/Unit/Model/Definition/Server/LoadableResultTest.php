<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Server\LoadableResult;

#[CoversClass(LoadableResult::class)]
#[Medium]
final class LoadableResultTest extends TestCase
{
    public function testCasesSpellEveryResultKeyword(): void
    {
        self::assertSame(['STRING', 'REAL', 'DECIMAL', 'INTEGER'], array_map(static fn (LoadableResult $result): string => $result->value, LoadableResult::cases()));
    }
}
