<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\DerivedAffinity;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\ExpressionAffinity;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\ValueKinds;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\NoAffinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ExpressionAffinity::class)]
#[Medium]
final class ExpressionAffinityTest extends TestCase
{
    public function testOfLooksThroughParenthesesAndCollateAndReadsCastsRowValuesAndSubqueries(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b INTEGER, c)');
        $query = $semantics->analyze('SELECT (a) COLLATE nocase, CAST(b AS REAL), CAST(b AS), (a, b), (SELECT b FROM t UNION ALL SELECT a FROM t), a || b, c, rowid, (VALUES (1), (CAST(1 AS TEXT))) FROM t', [$table]);
        $select = $query->statement;
        $rule = new ExpressionAffinity();

        self::assertInstanceOf(Select::class, $select);
        $affinities = array_map(static fn (int $position): ?Affinity => $rule->column($select, $position, $query->facts)->affinity, range(0, 8));
        self::assertSame([Affinity::Text, Affinity::Real, Affinity::Numeric, Affinity::Text, Affinity::Text, null, Affinity::Blob, Affinity::Integer, Affinity::Text], $affinities);
        self::assertTrue($rule->column($select, 5, $query->facts)->determined);
        self::assertSame(Affinity::Numeric, $rule->of(new Cast(new IntegerLiteral('1')), $query->facts)->affinity);
    }

    public function testReferenceReadsADoubleQuotedWordThatIsNoColumnAsAStringWithoutAffinity(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT)');
        $query = $semantics->analyze('SELECT "a", "zz", zz FROM t', [$table]);
        $select = $query->statement;
        $rule = new ExpressionAffinity();

        self::assertInstanceOf(Select::class, $select);
        $column = $select->columns[0];
        $string = $select->columns[1];
        $missing = $select->columns[2];
        self::assertInstanceOf(ResultColumn::class, $column);
        self::assertInstanceOf(ResultColumn::class, $string);
        self::assertInstanceOf(ResultColumn::class, $missing);
        self::assertSame(Affinity::Text, $rule->reference($column->expression, $query->facts)->affinity);
        self::assertNull($rule->reference($string->expression, $query->facts)->affinity);
        self::assertTrue($rule->reference($string->expression, $query->facts)->determined);
        self::assertFalse($rule->reference($missing->expression, $query->facts)->determined);
        self::assertFalse($rule->reference(new IntegerLiteral('1'), $query->facts)->determined);
    }

    public function testFieldReadsTheExpressionOrTheColumnAnExpandedStarExposes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b)');
        $query = $semantics->analyze('SELECT *, 1 FROM t', [$table]);
        $rule = new ExpressionAffinity();

        self::assertSame(Affinity::Text, $rule->field($query->field(0), $query->facts)->affinity);
        self::assertSame(Affinity::Blob, $rule->field($query->field(1), $query->facts)->affinity);
        self::assertNull($rule->field($query->field(2), $query->facts)->affinity);
        self::assertTrue($rule->field($query->field(2), $query->facts)->determined);
    }

    public function testResolvedReadsADeclarationADerivedTableAndACommonTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b INTEGER)');
        $query = $semantics->analyze('WITH w AS (SELECT CAST(b AS INT) AS x FROM t UNION ALL SELECT b FROM t) SELECT t.a, d.y, w.x FROM t, (SELECT b AS y FROM t) AS d, w', [$table]);
        $rule = new ExpressionAffinity();
        $declared = $query->field(0)->resolution;
        $derived = $query->field(1)->resolution;
        $common = $query->field(2)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $declared);
        self::assertInstanceOf(ResolvedColumn::class, $derived);
        self::assertInstanceOf(ResolvedColumn::class, $common);
        self::assertSame(Affinity::Text, $rule->resolved($declared, $query->facts)->affinity);
        self::assertSame(Affinity::Integer, $rule->resolved($derived, $query->facts)->affinity);
        self::assertSame(Affinity::Numeric, $rule->resolved($common, $query->facts)->affinity);
        self::assertTrue($rule->resolved($common, $query->facts)->flexible);
    }

    public function testDeclaredReadsEachKindOfTypeDescriptor(): void
    {
        $rule = new ExpressionAffinity();

        self::assertSame(Affinity::Text, $rule->declared(new Column(new Name('a'), new ColumnDomain('VARCHAR(5)')))->affinity);
        self::assertNull($rule->declared(new Column(new Name('a'), new NoAffinity()))->affinity);
        self::assertTrue($rule->declared(new Column(new Name('a'), new NoAffinity()))->determined);
        self::assertSame(Affinity::Real, $rule->declared(new Column(new Name('a'), Storage::Real))->affinity);
    }

    public function testComputingAnswersTheQueryOfADerivedTableOrACommonTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a)');
        $query = $semantics->analyze('WITH w AS (SELECT a FROM t) SELECT t.a, d.a, w.a FROM t, (SELECT a FROM t) AS d, w', [$table]);
        $rule = new ExpressionAffinity();
        $declared = $query->field(0)->resolution;
        $derived = $query->field(1)->resolution;
        $common = $query->field(2)->resolution;
        $with = $query->statement;

        self::assertInstanceOf(ResolvedColumn::class, $declared);
        self::assertInstanceOf(ResolvedColumn::class, $derived);
        self::assertInstanceOf(ResolvedColumn::class, $common);
        self::assertInstanceOf(WithQuery::class, $with);
        self::assertNull($rule->computing($declared->relation, $query->facts));
        self::assertInstanceOf(DerivedQuery::class, $derived->relation);
        self::assertSame($derived->relation->query, $rule->computing($derived->relation, $query->facts));
        self::assertSame($with->with->tables[0]->query, $rule->computing($common->relation, $query->facts));
    }

    public function testPositionFindsTheOutputFieldASlotReExposes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a, b)');
        $query = $semantics->analyze('SELECT d.b, d.a FROM (SELECT a, b FROM t UNION ALL SELECT b, a FROM t) AS d', [$table]);
        $derived = $query->inputRelation();
        $resolution = $query->field(0)->resolution;
        $select = $query->statement;
        $other = $semantics->analyze('SELECT 1')->statement;

        self::assertInstanceOf(DerivedQuery::class, $derived);
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Select::class, $other);
        self::assertSame(1, (new ExpressionAffinity())->position($derived->query, $resolution->slot, $query->facts));
        self::assertNull((new ExpressionAffinity())->position($select, $resolution->slot, $query->facts));
        self::assertNull((new ExpressionAffinity())->position($other, $resolution->slot, $query->facts));
    }

    public function testArmsFlattensWithClausesCompoundsAndValuesRows(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $compound = $semantics->analyze('WITH w AS (SELECT 1) SELECT 1 UNION ALL VALUES (2), (3) UNION SELECT 4')->statement;
        $values = $semantics->analyze('VALUES (1), (2)')->statement;
        $rule = new ExpressionAffinity();

        self::assertInstanceOf(ValuesClause::class, $values);
        self::assertInstanceOf(WithQuery::class, $compound);
        self::assertInstanceOf(Compound::class, $compound->body);
        $arms = $rule->arms($compound);
        self::assertCount(4, $arms);
        self::assertInstanceOf(Select::class, $arms[0]);
        self::assertInstanceOf(ValueRow::class, $arms[1]);
        self::assertInstanceOf(ValueRow::class, $arms[2]);
        self::assertInstanceOf(Select::class, $arms[3]);
        self::assertSame($compound->body->first, $arms[0]);
        self::assertCount(2, $rule->arms($values));
        self::assertCount(1, $rule->arms($compound->body->first));
    }

    public function testArmReadsAPositionOfASelectionOrARow(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT)');
        $query = $semantics->analyze('SELECT *, CAST(1 AS INT) FROM t', [$table]);
        $rows = $semantics->analyze("VALUES (1, 'x')");
        $values = $rows->statement;
        $select = $query->statement;
        $rule = new ExpressionAffinity();

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(ValuesClause::class, $values);
        self::assertSame(Affinity::Text, $rule->arm($select, 0, $query->facts)->affinity);
        self::assertSame(Affinity::Integer, $rule->arm($select, 1, $query->facts)->affinity);
        self::assertFalse($rule->arm($select, 2, $query->facts)->determined);
        self::assertNull($rule->arm($values->rows[0], 1, $rows->facts)->affinity);
        self::assertFalse($rule->arm($values->rows[0], 2, $rows->facts)->determined);
    }

    public function testWrittenReadsTheExpressionOfAPositionAndNothingForAStar(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT)');
        $query = $semantics->analyze('SELECT *, CAST(1 AS INT) FROM t', [$table]);
        $rows = $semantics->analyze("VALUES (1, 'x')");
        $values = $rows->statement;
        $select = $query->statement;
        $rule = new ExpressionAffinity();

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(ValuesClause::class, $values);
        self::assertNull($rule->written($select, 0, $query->facts));
        self::assertInstanceOf(Cast::class, $rule->written($select, 1, $query->facts));
        self::assertNull($rule->written($select, 2, $query->facts));
        self::assertSame($values->rows[0]->values[1], $rule->written($values->rows[0], 1, $rows->facts));
        self::assertNull($rule->written($values->rows[0], 2, $rows->facts));
    }

    public function testFirstReadsTheFirstResultOfTheRightmostArm(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b INTEGER)');
        $query = $semantics->analyze('SELECT (SELECT a FROM t UNION ALL SELECT b FROM t), (VALUES (1), (CAST(1 AS BLOB))), (SELECT * FROM u) FROM t', [$table]);
        $select = $query->statement;
        $rule = new ExpressionAffinity();

        self::assertInstanceOf(Select::class, $select);
        self::assertSame(Affinity::Integer, $rule->column($select, 0, $query->facts)->affinity);
        self::assertSame(Affinity::Blob, $rule->column($select, 1, $query->facts)->affinity);
        self::assertFalse($rule->column($select, 2, $query->facts)->determined);
    }

    public function testColumnAdjustsACompoundByTheOtherArms(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b INTEGER, c, d DECIMAL)');
        $query = $semantics->analyze("SELECT a, b, b, a, CAST(b AS INT), 1, NULL, CAST(d AS NUMERIC), c FROM t UNION ALL SELECT 1, 'x', b, a, b, b, a, d, a FROM t", [$table]);
        $compound = $query->statement;
        $rule = new ExpressionAffinity();

        self::assertInstanceOf(Compound::class, $compound);
        $affinities = array_map(static fn (int $position): ?Affinity => $rule->column($compound, $position, $query->facts)->affinity, range(0, 8));
        self::assertSame([Affinity::Blob, Affinity::Blob, Affinity::Integer, Affinity::Text, Affinity::Numeric, Affinity::Integer, Affinity::Text, Affinity::Numeric, Affinity::Blob], $affinities);
        self::assertTrue($rule->column($compound, 4, $query->facts)->flexible);
        self::assertTrue($rule->column($compound, 7, $query->facts)->flexible);
        self::assertFalse($rule->column($compound->first, 4, $query->facts)->flexible);
    }

    public function testColumnIsNotDeterminedWhenAnArmDependsOnAMissingDeclaration(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT)');
        $query = $semantics->analyze('SELECT t.a FROM t UNION ALL SELECT u.x FROM u', [$table]);
        $compound = $query->statement;

        self::assertInstanceOf(Compound::class, $compound);
        self::assertFalse((new ExpressionAffinity())->column($compound, 0, $query->facts)->determined);
    }

    public function testAdjustedAppliesTheRulesOfACompoundColumn(): void
    {
        $rule = new ExpressionAffinity();
        $cast = new Cast(new IntegerLiteral('1'));

        self::assertSame(Affinity::Blob, $rule->adjusted(new DerivedAffinity(Affinity::Text), ValueKinds::NUMBER, null)->affinity);
        self::assertSame(Affinity::Text, $rule->adjusted(new DerivedAffinity(Affinity::Text), ValueKinds::TEXT | ValueKinds::BLOB, null)->affinity);
        self::assertSame(Affinity::Blob, $rule->adjusted(new DerivedAffinity(Affinity::Integer), ValueKinds::TEXT, $cast)->affinity);
        self::assertSame(Affinity::Real, $rule->adjusted(new DerivedAffinity(Affinity::Real), ValueKinds::NUMBER, null)->affinity);
        self::assertTrue($rule->adjusted(new DerivedAffinity(Affinity::Real), ValueKinds::NUMBER, $cast)->flexible);
        self::assertTrue($rule->adjusted(new DerivedAffinity(Affinity::Numeric, true), 0, null)->flexible);
    }
}
