<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Exists::class)]
#[Medium]
final class ExistsTest extends TestCase
{
    public function testDeriveScalarIsAnIntegerThatIsNeverNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT EXISTS (SELECT b FROM t), EXISTS (SELECT * FROM x)', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertInstanceOf(Known::class, $query->field(1)->type);
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
    }

    public function testDeriveScalarRecordsTheFactOfTheQuery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT EXISTS (SELECT a, b FROM t)', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $exists = $query->field(0)->expression;

        self::assertInstanceOf(Exists::class, $exists);
        self::assertCount(2, $query->facts->query($exists->query)->shape->slots);
        self::assertTrue($query->facts->query($exists->query)->shape->complete());
    }

    public function testDeriveScalarDerivesTheQueryInsideTheEnvironmentOfTheExpression(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a FROM t WHERE EXISTS (SELECT 1 WHERE a = 1)', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $select = $query->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Exists::class, $select->where);
        self::assertInstanceOf(Select::class, $select->where->query);
        self::assertInstanceOf(Binary::class, $select->where->query->where);
        $resolution = $query->facts->scalar($select->where->query->where->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame(1, $resolution->depth);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testRenderWritesTheKeywordAndTheParentheses(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('select exists (select 1 from t) AS c1');
        $negated = $semantics->analyze('SELECT NOT EXISTS (SELECT 1 FROM t) AS c1');

        self::assertSame('SELECT EXISTS (SELECT 1 FROM t) AS c1', $plain->toString());
        self::assertInstanceOf(Unary::class, $negated->field(0)->expression);
        self::assertInstanceOf(Exists::class, $negated->field(0)->expression->operand);
        self::assertSame('SELECT NOT EXISTS (SELECT 1 FROM t) AS c1', $negated->toString());
    }

    public function testRenderWritesANewlyBuiltTest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $exists = new Exists(new Select([new ResultColumn(new IntegerLiteral('1'))]));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn($exists)]));

        self::assertSame('SELECT EXISTS (SELECT 1)', $operation->toString());
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }
}
