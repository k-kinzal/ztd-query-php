<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\CopyCollation;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(CopyCollation::class)]
#[Medium]
final class CopyCollationTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Inspection())->inspect('CREATE COLLATION c FROM d', []);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesIfNotExists(): void
    {
        $operation = (new Inspection())->inspect('CREATE COLLATION IF NOT EXISTS s.c FROM "C"');
        self::assertSame('CREATE COLLATION IF NOT EXISTS s.c FROM "C"', $operation->toString());
    }
}
