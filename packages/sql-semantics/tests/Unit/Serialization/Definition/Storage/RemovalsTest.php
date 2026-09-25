<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Storage\Removals::class)]
#[Medium]
final class RemovalsTest extends TestCase
{
    public function testWriteReturnsNullForAnotherOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\Storage\Removals::write($statement));
    }

    #[TestWith(['DROP TABLESPACE store', 'DROP TABLESPACE `store` WAIT'])]
    #[TestWith(['DROP LOGFILE GROUP logs NO_WAIT', 'DROP LOGFILE GROUP `logs` NO_WAIT'])]
    #[TestWith(['DROP UNDO TABLESPACE undo1 ENGINE InnoDB', 'DROP UNDO TABLESPACE `undo1` ENGINE `InnoDB`'])]
    public function testWriteReconstructsOnlyTheConcreteOperationOperands(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $first = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($first));
        $second = $binder->bind($expected);
        self::assertSame($first::class, $second::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($second));
    }
}
