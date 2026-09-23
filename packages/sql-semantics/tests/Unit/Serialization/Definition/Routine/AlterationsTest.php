<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Routine\Alterations::class)]
#[Medium]
final class AlterationsTest extends TestCase
{
    public function testWriteReturnsNullForAnUnrelatedStatement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\Routine\Alterations::write($statement));
    }

    #[TestWith(['ALTER FUNCTION f', 'ALTER FUNCTION `f`'])]
    #[TestWith(['ALTER PROCEDURE app.p LANGUAGE JavaScript', 'ALTER PROCEDURE `app`.`p` LANGUAGE `JavaScript`'])]
    #[TestWith(["ALTER FUNCTION f COMMENT 'note' LANGUAGE SQL NO SQL", "ALTER FUNCTION `f` LANGUAGE SQL NO SQL COMMENT 'note'"])]
    public function testWriteReconstructsStableCharacteristics(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $first = $binder->bind($sql);
        self::assertSame($expected, $first->toString());
        $second = $binder->bind($expected);
        self::assertSame($first::class, $second::class);
        self::assertSame($expected, $second->toString());
    }
}
