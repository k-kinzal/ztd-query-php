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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($first));
        $second = $binder->bind($expected);
        self::assertSame($first::class, $second::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($second));
    }

    #[TestWith(['ALTER PROCEDURE p SQL SECURITY INVOKER', \SqlSemantics\Model\Statement\Definition\MySql\AlterProcedureStatement::class, 'ALTER PROCEDURE `p` SQL SECURITY INVOKER'])]
    #[TestWith(['ALTER FUNCTION f SQL SECURITY DEFINER COMMENT "c"', \SqlSemantics\Model\Statement\Definition\MySql\AlterFunctionStatement::class, 'ALTER FUNCTION `f` SQL SECURITY DEFINER COMMENT "c"'])]
    public function testWriteSpellsTheSecurityContext(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
