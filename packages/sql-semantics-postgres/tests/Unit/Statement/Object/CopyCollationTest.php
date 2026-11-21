<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Object\CopyCollation;

#[CoversClass(CopyCollation::class)]
#[Medium]
final class CopyCollationTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Semantics())->analyze('CREATE COLLATION c FROM d', []);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesIfNotExists(): void
    {
        $operation = (new Semantics())->analyze('CREATE COLLATION IF NOT EXISTS s.c FROM "C"');
        self::assertSame('CREATE COLLATION IF NOT EXISTS s.c FROM "C"', $operation->toString());
    }
}
