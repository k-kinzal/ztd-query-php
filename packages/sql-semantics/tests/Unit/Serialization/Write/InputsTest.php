<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Insert\InsertValuesStatement;
use SqlSemantics\Model\Write\DefaultSource;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Write\Inputs;

#[CoversClass(Inputs::class)]
#[Medium]
final class InputsTest extends TestCase
{
    public function testWriteSpellsADestinationDefaultAsTheDefaultKeyword(): void
    {
        self::assertSame('DEFAULT', Inputs::write(DefaultSource::Column)->toString());
    }

    public function testWriteDelegatesExpressionsToTheExpressionSerializer(): void
    {
        self::assertSame("'it''s'", Inputs::write(Expression::literal("it's", Dialect::PostgreSql))->toString());
        self::assertSame('NULL', Inputs::write(Expression::literal(null, Dialect::MySql))->toString());
    }

    public function testWriteKeepsBoundRowSlotsInPosition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('INSERT INTO t (id, n) VALUES (1, DEFAULT), (DEFAULT, 2)');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        self::assertSame(DefaultSource::Column, $statement->rows[0][1]);
        self::assertSame(['1', 'DEFAULT'], array_map(static fn (Expression|DefaultSource $slot): string => Inputs::write($slot)->toString(), $statement->rows[0]));
        self::assertSame(['DEFAULT', '2'], array_map(static fn (Expression|DefaultSource $slot): string => Inputs::write($slot)->toString(), $statement->rows[1]));
        self::assertSame('INSERT INTO "public"."t"("id", "n") VALUES (1, DEFAULT), (DEFAULT, 2)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
