<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Ordering\OutputAlias;
use SqlSemantics\Model\Query\Ordering\OutputPosition;
use SqlSemantics\Model\Query\Ordering\UnresolvedOutputPosition;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\OrderingKeys;

#[CoversClass(OrderingKeys::class)]
#[Medium]
final class OrderingKeysTest extends TestCase
{
    public function testWriteSpellsAnOutputPositionFromItsOrdinal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)')))->bind('SELECT n, id FROM t ORDER BY 2');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $key = $statement->orderBy[0]->key;
        self::assertInstanceOf(OutputPosition::class, $key);
        self::assertSame(1, $key->output->ordinal);
        self::assertSame('2', OrderingKeys::write($key)->toString());
        self::assertSame('SELECT "n" AS "n", "id" AS "id" FROM "public"."t" ORDER BY 2 ASC', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWriteQuotesAnOutputAliasForItsDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('SELECT id AS x FROM t ORDER BY x');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $key = $statement->orderBy[0]->key;
        self::assertInstanceOf(OutputAlias::class, $key);
        self::assertSame('x', $key->output->name);
        self::assertSame('`x`', OrderingKeys::write($key)->toString());
        self::assertSame('SELECT `id` AS `x` FROM `t` ORDER BY `x` ASC', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWriteSerializesAnExpressionKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('SELECT id FROM t ORDER BY id + 1 DESC');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $key = $statement->orderBy[0]->key;
        self::assertInstanceOf(BinaryExpression::class, $key);
        self::assertSame('("id" + 1)', OrderingKeys::write($key)->toString());
        self::assertSame('SELECT "id" AS "id" FROM "public"."t" ORDER BY("id" + 1) DESC', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWriteKeepsAnUnresolvedPositionSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM missing ORDER BY 3', strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $key = $statement->orderBy[0]->key;
        self::assertInstanceOf(UnresolvedOutputPosition::class, $key);
        self::assertSame('3', $key->position->spelling);
        self::assertSame('3', OrderingKeys::write($key)->toString());
        self::assertSame('SELECT "missing".* FROM "public"."missing" ORDER BY 3 ASC', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWriteRejectsAnAliasWithoutAName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertNull($statement->outputs[0]->name);
        $this->expectException(InvalidStructure::class);
        OrderingKeys::write(new OutputAlias($statement->outputs[0]));
    }
}
