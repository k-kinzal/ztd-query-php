<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\AbsentField;
use SqlSemantics\Statement\Shape\AmbiguousFields;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\UniqueField;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Select::class)]
#[Medium]
final class SelectTest extends TestCase
{
    public function testInputAnswersTheTableReference(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        self::assertSame($operation->statement->from, $operation->statement->input());
        self::assertSame($operation->statement->from, $operation->inputRelation());
    }

    public function testInputAnswersNullWithoutAFromClause(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertNull($operation->statement->input());
        self::assertNull($operation->inputRelation());
    }

    public function testDeriveStatementRecordsTheRowsAsTheOutput(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $operation = $semantics->analyze('SELECT a, b FROM t', [$table]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame($operation->facts->query($operation->statement), $operation->facts->output);
        self::assertNotNull($operation->shape());
        self::assertTrue($operation->shape()->complete());
        self::assertCount(2, $operation->shape()->slots);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveQueryNamesTheFieldsAfterAliasesAndColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $operation = $semantics->analyze('SELECT a AS x, b, 1 FROM t', [$table]);

        $fields = $operation->fields();
        self::assertNotNull($fields);
        self::assertSame(['x', 'b', null], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
        self::assertSame($table->columns[0], $operation->field('x')->column());
        self::assertSame($table->columns[1], $operation->field('b')->column());
        self::assertNull($operation->field(2)->column());
        $type = $operation->field('x')->type;
        self::assertInstanceOf(Known::class, $type);
        self::assertSame('INT', $type->descriptor->name());
        self::assertSame(Nullability::NotNull, $operation->field('x')->nullability);
        self::assertSame(Nullability::Nullable, $operation->field('b')->nullability);
        self::assertInstanceOf(ResolvedColumn::class, $operation->field('x')->resolution);
        self::assertInstanceOf(UniqueField::class, $operation->lookupField('X'));
        self::assertInstanceOf(AbsentField::class, $operation->lookupField('a'));
    }

    public function testDeriveQueryLeavesTheNameOpenForAnExpressionItem(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1');

        $fields = $operation->fields();
        self::assertNotNull($fields);
        self::assertCount(1, $fields);
        self::assertNull($fields->at(0)->slot->name);
        self::assertInstanceOf(Known::class, $fields->at(0)->type);
        self::assertSame(Nullability::NotNull, $fields->at(0)->nullability);
        self::assertNull($fields->at(0)->resolution);
    }

    public function testDeriveQueryKeepsDuplicateOutputNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT a, a FROM t', [$table]);

        self::assertNotNull($operation->fields());
        self::assertCount(2, $operation->fields());
        self::assertInstanceOf(AmbiguousFields::class, $operation->lookupField('a'));
    }

    public function testDeriveQueryResolvesAQualifiedColumnAgainstTheTableOccurrence(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $byTable = $semantics->analyze('SELECT t.a FROM t', [$table]);
        $byAlias = $semantics->analyze('SELECT u.a FROM t AS u', [$table]);

        self::assertSame($table->columns[0], $byTable->field('a')->column());
        self::assertSame([], $byTable->facts->diagnostics);
        self::assertSame($table->columns[0], $byAlias->field('a')->column());
        self::assertSame([], $byAlias->facts->diagnostics);
        self::assertSame('SELECT t.a FROM t', $byTable->toString());
        self::assertSame('SELECT u.a FROM t AS u', $byAlias->toString());
    }

    public function testDeriveQueryReportsAColumnWithAWrongQualifier(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT u.a FROM t', [$table]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
        self::assertSame('Column u.a does not exist.', $operation->facts->diagnostics[0]->message());
        self::assertInstanceOf(Invalid::class, $operation->field('a')->type);
        self::assertNull($operation->field('a')->column());
    }

    public function testDeriveQueryDerivesThePredicateInTheScopeOfTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $operation = $semantics->analyze('SELECT a FROM t WHERE b = 1', [$table]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        $left = $operation->facts->scalar($operation->statement->where->left);
        self::assertInstanceOf(ResolvedColumn::class, $left->resolution);
        self::assertSame($table->columns[1], $left->resolution->declaration());
        self::assertSame($operation->statement->from, $left->resolution->relation);
        self::assertSame(Nullability::Nullable, $operation->facts->scalar($operation->statement->where)->nullability);
    }

    public function testDeriveQueryDependsOnAnUndeclaredTable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t');

        $type = $operation->field('a')->type;
        self::assertInstanceOf(Dependent::class, $type);
        self::assertSame('the declaration of relation t', $type->missing[0]->describe());
        self::assertSame(Nullability::Dependent, $operation->field('a')->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveQueryReportsAMissingTableInACompleteContext(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t', []);

        self::assertCount(2, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
        self::assertSame('Relation t does not exist.', $operation->facts->diagnostics[0]->message());
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[1]);
        self::assertSame('Column a does not exist.', $operation->facts->diagnostics[1]->message());
    }

    public function testRenderWritesTheClausesInGrammarOrder(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT a, b AS c FROM t WHERE a <=> 1', $semantics->analyze('select a, b as c from t where a <=> 1')->toString());
        self::assertSame('SELECT a FROM t', $semantics->analyze('SELECT a FROM t')->toString());
        self::assertSame('SELECT 1', $semantics->analyze('select 1')->toString());
    }

    public function testRenderIsTheSameInEveryGrammarGeneration(): void
    {
        self::assertSame('SELECT a, b AS c FROM t WHERE a <=> 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a, b as c from t where a <=> 1')->toString());
        self::assertSame('SELECT a, b AS c FROM t WHERE a <=> 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a, b as c from t where a <=> 1')->toString());
        self::assertSame('SELECT a, b AS c FROM t WHERE a <=> 1', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select a, b as c from t where a <=> 1')->toString());
    }

    public function testRenderReadsADoubleQuotedNameUnderAnsiQuotes(): void
    {
        $semantics = new Semantics(Dialect::MySql, null, Mode::fromString('ANSI_QUOTES'));
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT "a" FROM t', [$table]);

        self::assertSame($table->columns[0], $operation->field('a')->column());
        self::assertSame('SELECT a FROM t', $operation->toString());
    }

    public function testRenderBuildsSqlFromAnExplicitStructure(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $select = new Select(
            [new SelectExpression(new ColumnUse(new Name('a')), new Name('x'))],
            new TableReference(new QualifiedName(new Name('t')), new Name('u')),
            new Comparison(ComparisonOperator::Less, new ColumnUse(new Name('a'), new QualifiedName(new Name('u'))), new NumberLiteral('3')),
        );
        $operation = new Operation($semantics->context([$table]), $select);

        self::assertSame('SELECT a AS x FROM t AS u WHERE u.a < 3', $operation->toString());
        self::assertSame($table->columns[0], $operation->field('x')->column());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testAnEmptySelectListIsRejected(): void
    {
        $this->expectExceptionMessage('A selection projects at least one expression.');

        new Select([]);
    }

    public function testANodeUsedAtTwoPositionsIsRejected(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $column = new ColumnUse(new Name('a'));

        $this->expectExceptionMessage('A statement node occurs at one position only.');

        new Operation($semantics->context(), new Select([new SelectExpression($column)], new TableReference(new QualifiedName(new Name('t'))), $column));
    }

    public function testAStringLiteralUnderAnotherEscapeRuleIsRejected(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        $this->expectExceptionMessage('A string literal must be spelled under the escape rule of the language profile.');

        new Operation($semantics->context(), new Select([new SelectExpression(new StringLiteral(['x'], EscapeRule::Verbatim))]));
    }
}
