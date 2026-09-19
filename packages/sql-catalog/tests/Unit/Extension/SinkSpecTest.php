<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\SinkCallKind;
use SqlCatalog\Extension\SinkRole;
use SqlCatalog\Extension\SinkSpec;

#[CoversClass(SinkSpec::class)]
final class SinkSpecTest extends TestCase
{
    public function testMatchesNameIgnoresCaseAndLeadingBackslashes(): void
    {
        $sink = new SinkSpec('id', SinkCallKind::FunctionCall, null, 'mysqli_query', SinkRole::Query, sqlParameter: 1);
        self::assertTrue($sink->matchesName('MYSQLI_QUERY'));
        self::assertTrue($sink->matchesName('\\mysqli_query'));
        self::assertFalse($sink->matchesName('mysqli_prepare'));
    }

    public function testTheArgumentPositionsAreKept(): void
    {
        $sink = new SinkSpec(
            'id',
            SinkCallKind::Method,
            'PDO',
            'prepare',
            SinkRole::Prepare,
            sqlParameter: 0,
            handleType: 'PDOStatement',
        );
        self::assertSame(0, $sink->sqlParameter);
        self::assertSame('PDOStatement', $sink->handleType);
        self::assertNull($sink->valuesParameter);
    }
}
