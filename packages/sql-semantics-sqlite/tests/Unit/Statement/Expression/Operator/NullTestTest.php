<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NullTest::class)]
#[Medium]
final class NullTestTest extends TestCase
{
    public function testDeriveScalarIsAnIntegerThatIsNeverNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a ISNULL, b NOTNULL, b NOT NULL, NULL ISNULL, ? NOTNULL FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $nullability = array_map(static fn (Field $field): Nullability => $field->nullability, $query->fields()->items ?? []);

        self::assertSame([Nullability::NotNull, Nullability::NotNull, Nullability::NotNull, Nullability::NotNull, Nullability::NotNull], $nullability);
        self::assertInstanceOf(Known::class, $query->field(3)->type);
        self::assertSame(Storage::Integer, $query->field(3)->type->descriptor);
        self::assertInstanceOf(Known::class, $query->field(4)->type);
        self::assertSame(Storage::Integer, $query->field(4)->type->descriptor);
    }

    public function testDeriveScalarRecordsTheFactOfTheOperand(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT b ISNULL FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $test = $query->field(0)->expression;

        self::assertInstanceOf(NullTest::class, $test);
        self::assertInstanceOf(ResolvedColumn::class, $query->facts->scalar($test->operand)->resolution);
        self::assertSame(Nullability::Nullable, $query->facts->scalar($test->operand)->nullability);
    }

    public function testRenderWritesEachForm(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select a isnull AS c1, a notnull AS c2, a not null AS c3 from t');

        self::assertSame('SELECT a ISNULL AS c1, a NOTNULL AS c2, a NOT NULL AS c3 FROM t', $query->toString());
    }

    public function testRenderAppliesToAnOperandOfTheEqualityGroup(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $membership = $semantics->analyze('SELECT 1 IN (2) ISNULL');
        $repeated = $semantics->analyze('SELECT 1 ISNULL ISNULL');
        $outer = $repeated->field(0)->expression;

        self::assertInstanceOf(NullTest::class, $membership->field(0)->expression);
        self::assertInstanceOf(InList::class, $membership->field(0)->expression->operand);
        self::assertSame('SELECT 1 IN (2) ISNULL', $membership->toString());
        self::assertInstanceOf(NullTest::class, $outer);
        self::assertInstanceOf(NullTest::class, $outer->operand);
        self::assertSame('SELECT 1 ISNULL ISNULL', $repeated->toString());
    }

    public function testRenderWritesANewlyBuiltTest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new NullTest(new NullLiteral(), NullTestForm::NotNullWords))]));

        self::assertSame('SELECT NULL NOT NULL', $operation->toString());
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testRefusesAnOperandThatEndsInAWeakerOperator(): void
    {
        $disjunction = new Binary(BinaryOperator::Or, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The operand needs parentheses to keep its place.');

        new NullTest($disjunction, NullTestForm::IsNull);
    }
}
