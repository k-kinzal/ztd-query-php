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
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InTable::class)]
#[Medium]
final class InTableTest extends TestCase
{
    public function testDeriveScalarReadsADeclaredTableAndIsNotNullWhenNothingCanBeNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL)');
        $query = $semantics->analyze('SELECT 1 IN t', [$create]);
        $test = $query->field(0)->expression;

        self::assertInstanceOf(InTable::class, $test);
        self::assertSame('t', $test->table->name->value);
        self::assertNull($test->arguments);
        self::assertInstanceOf(DeclaredTable::class, $test->relationInformation()->table);
        self::assertSame($create->declarations()[0], $test->relationInformation()->table->table);
        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertSame([], $query->diagnostics());
    }

    public function testDeriveScalarCanBeNullWhenTheOperandOrTheColumnCan(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $nullable = $semantics->analyze('SELECT 1 IN u', [$semantics->analyze('CREATE TABLE u (a INTEGER)')]);
        $operand = $semantics->analyze('SELECT NULL IN t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL)')]);

        self::assertSame(Nullability::Nullable, $nullable->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operand->field(0)->nullability);
    }

    public function testDeriveScalarReportsATableOfAnotherWidth(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $single = $semantics->analyze('SELECT 1 IN t', [$create]);
        $row = $semantics->analyze('SELECT (1, 2) IN t', [$create]);

        self::assertCount(1, $single->diagnostics());
        self::assertInstanceOf(ArityMismatch::class, $single->diagnostics()[0]);
        self::assertSame(ArityRule::ScalarSubquery, $single->diagnostics()[0]->rule);
        self::assertSame('Sub-select returns 2 columns - expected 1.', $single->diagnostics()[0]->message());
        self::assertSame([], $row->diagnostics());
        self::assertSame(Nullability::Nullable, $row->field(0)->nullability);
    }

    public function testDeriveScalarReadsACommonTable(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('WITH c AS (SELECT 1 AS x) SELECT 1 IN c');
        $test = $query->field(0)->expression;

        self::assertInstanceOf(InTable::class, $test);
        self::assertInstanceOf(CommonTable::class, $test->relationInformation()->table);
        self::assertSame('x', $test->relationInformation()->shape->slots[0]->name?->value);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
    }

    public function testDeriveScalarDependsOnAnUndeclaredTable(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 IN x');
        $test = $query->field(0)->expression;

        self::assertInstanceOf(InTable::class, $test);
        self::assertInstanceOf(UndeclaredTable::class, $test->relationInformation()->table);
        self::assertFalse($test->relationInformation()->shape->complete());
        self::assertSame('the declaration of relation x', $test->relationInformation()->shape->missing[0]->describe());
        self::assertSame(Nullability::Dependent, $query->field(0)->nullability);
        self::assertSame([], $query->diagnostics());
    }

    public function testDeriveScalarDependsOnTheRoutineOfATableValuedFunction(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 IN json_each(1)', []);
        $test = $query->field(0)->expression;

        self::assertInstanceOf(InTable::class, $test);
        self::assertCount(1, $test->arguments ?? []);
        self::assertNull($test->relationInformation()->table);
        self::assertInstanceOf(UndeclaredRoutine::class, $test->relationInformation()->shape->missing[0]);
        self::assertSame('json_each', $test->relationInformation()->shape->missing[0]->name->name->value);
        self::assertSame(Nullability::Dependent, $query->field(0)->nullability);
        self::assertSame([], $query->diagnostics());
    }

    public function testDeriveScalarReportsAMissingTableInACompleteContext(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 IN x', []);
        $test = $query->field(0)->expression;

        self::assertInstanceOf(InTable::class, $test);
        self::assertInstanceOf(MissingTable::class, $test->relationInformation()->table);
        self::assertInstanceOf(MissingTable::class, $query->diagnostics()[0]);
        self::assertSame('Relation x does not exist.', $query->diagnostics()[0]->message());
    }

    public function testRenderWritesTheNegationTheSchemaAndTheArguments(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $qualified = $semantics->analyze('select a not in main.t AS c1 from t');
        $call = $semantics->analyze('SELECT 1 IN json_each(1, 2) AS c1');
        $test = $qualified->field(0)->expression;

        self::assertInstanceOf(InTable::class, $test);
        self::assertTrue($test->negated);
        self::assertSame('main', $test->table->schema?->value);
        self::assertSame('SELECT a NOT IN main.t AS c1 FROM t', $qualified->toString());
        self::assertSame('SELECT 1 IN json_each(1, 2) AS c1', $call->toString());
    }

    public function testRenderWritesANewlyBuiltTest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $test = new InTable(new IntegerLiteral('1'), new QualifiedName(new Name('t'), new Name('temp')), null, true);
        $operation = Semantics::fromStatement($semantics->context(), new Select([new ResultColumn($test)]));

        self::assertSame('SELECT 1 NOT IN `temp`.t', $operation->toString());
    }

    public function testRefusesAnOperandThatEndsInAWeakerOperator(): void
    {
        $disjunction = new Binary(BinaryOperator::Or, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The operand needs parentheses to keep its place.');

        new InTable($disjunction, new QualifiedName(new Name('t')));
    }
}
