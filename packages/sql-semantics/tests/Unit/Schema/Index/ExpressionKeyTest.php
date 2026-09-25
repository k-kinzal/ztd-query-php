<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Schema\Index\ExpressionKey;
use SqlSemantics\Schema\Index\NullOrder;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExpressionKey::class)]
#[Medium]
final class ExpressionKeyTest extends TestCase
{
    public function testValueIsTheComputedExpression(): void
    {
        $expression = Expression::binary('+', Expression::reference(['id'], Dialect::PostgreSql), Expression::literal(1, Dialect::PostgreSql));
        $key = new ExpressionKey($expression, nulls: NullOrder::Last);
        self::assertSame($expression, $key->value());
        self::assertSame('("id" + 1)', $key->value()->structure()->toString());
        self::assertSame(NullOrder::Last, $key->nulls);
        self::assertNull($key->direction);
        self::assertNull($key->collation);
        self::assertNull($key->operatorClass);
    }

    public function testBindsAComputedKeyAndSerializesItInParentheses(): void
    {
        $binder = new \SqlSemantics\Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('CREATE INDEX ix ON t((id + 1) ASC)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $statement);
        $key = $statement->index->definition->elements[0];
        self::assertInstanceOf(ExpressionKey::class, $key);
        self::assertSame('("id" + 1)', $key->value()->structure()->toString());
        self::assertSame('CREATE INDEX "ix" ON "public"."t"((("id" + 1)) ASC)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
