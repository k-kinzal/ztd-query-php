<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ExpressionAffinity;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(ExpressionAffinity::class)]
#[Medium]
final class ExpressionAffinityTest extends TestCase
{
    public function testColumnFindsTheDeclaredColumnBehindParenthesesAndCollations(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b INTEGER)');
        $query = $semantics->analyze('SELECT (a) COLLATE nocase, b + 1, 1 FROM t', [$table]);
        $rule = new ExpressionAffinity();

        self::assertSame($table->declarations()[0]->columns[0], $rule->column($query->field(0)->expression, $query->facts));
        self::assertNull($rule->column($query->field(1)->expression, $query->facts));
        self::assertNull($rule->column(null, $query->facts));
    }

    public function testFirstAnswersTheFirstResultOfTheRightmostArm(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)');
        $query = $semantics->analyze('SELECT (SELECT a FROM t UNION ALL SELECT b FROM t), (WITH w AS (SELECT 1) SELECT * FROM t), (VALUES (1), (2)), (SELECT * FROM u) FROM t', [$table]);
        $rule = new ExpressionAffinity();
        $subqueries = array_map(static fn (object $field): object => $field->expression, iterator_to_array($query->fields() ?? []));

        self::assertContainsOnlyInstancesOf(ScalarSubquery::class, $subqueries);
        $compound = $rule->first($subqueries[0]->query, $query->facts);
        self::assertNotNull($compound);
        self::assertSame($table->declarations()[0]->columns[1], $rule->column($compound, $query->facts));
        self::assertSame($table->declarations()[0]->columns[0], $rule->first($subqueries[1]->query, $query->facts));
        self::assertInstanceOf(IntegerLiteral::class, $rule->first($subqueries[2]->query, $query->facts));
        self::assertSame('2', $rule->first($subqueries[2]->query, $query->facts)->digits);
        self::assertNull($rule->first($subqueries[3]->query, $query->facts));
    }

    public function testExposedFindsTheFieldASlotReExposes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $query = $semantics->analyze('SELECT x FROM (SELECT CAST(a AS TEXT) AS x FROM t) AS d', [$table]);
        $resolution = $query->field(0)->resolution;
        $derived = $query->inputRelation();

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertNotNull($derived);
        $inner = $query->statement;
        self::assertInstanceOf(Select::class, $inner);
        self::assertSame($inner, $query->statement);
        $field = (new ExpressionAffinity())->exposed($derived->query, $resolution->slot, $query->facts);
        self::assertInstanceOf(Field::class, $field);
        self::assertInstanceOf(Cast::class, $field->expression);
        self::assertNull((new ExpressionAffinity())->exposed($query->statement, $resolution->slot, $query->facts));
    }

    public function testResolvedReadsADeclaredColumnOrTheFieldOfADerivedOrCommonTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $query = $semantics->analyze('WITH w AS (SELECT a + 1 AS y FROM t) SELECT t.a, d.x, w.y, d.z FROM t, (SELECT CAST(a AS TEXT) AS x, 1 AS z FROM t) AS d, w', [$table]);
        $rule = new ExpressionAffinity();
        $resolutions = array_map(static fn (object $field): object => $field->resolution, iterator_to_array($query->fields() ?? []));

        self::assertContainsOnlyInstancesOf(ResolvedColumn::class, $resolutions);
        self::assertSame($table->declarations()[0]->columns[0], $rule->resolved($resolutions[0], $query->facts));
        $derived = $rule->resolved($resolutions[1], $query->facts);
        self::assertInstanceOf(Field::class, $derived);
        self::assertInstanceOf(Cast::class, $derived->expression);
        $common = $rule->resolved($resolutions[2], $query->facts);
        self::assertInstanceOf(Field::class, $common);
        self::assertSame('y', $common->name?->value);
        $literal = $rule->resolved($resolutions[3], $query->facts);
        self::assertInstanceOf(Field::class, $literal);
        self::assertInstanceOf(IntegerLiteral::class, $literal->expression);
    }

    public function testSourceFollowsSubqueriesAndDerivedColumnsToAColumnOrACast(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)');
        $query = $semantics->analyze('SELECT (SELECT (a) FROM t), ((SELECT b FROM t UNION ALL SELECT a FROM t)), d.x, d.y, (SELECT 1), (SELECT * FROM u) FROM (SELECT CAST(a AS REAL) AS x, a + 1 AS y FROM t) AS d', [$table]);
        $rule = new ExpressionAffinity();
        $sources = array_map(static fn (object $field): object|null => $rule->source($field->expression, $query->facts), iterator_to_array($query->fields() ?? []));

        self::assertSame($table->declarations()[0]->columns[0], $sources[0]);
        self::assertSame($table->declarations()[0]->columns[0], $sources[1]);
        self::assertInstanceOf(Cast::class, $sources[2]);
        self::assertNull($sources[3]);
        self::assertNull($sources[4]);
        self::assertNull($sources[5]);
        self::assertNull($rule->source(null, $query->facts));
    }

    public function testStepTurnsAFieldIntoItsExpressionOrItsColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $query = $semantics->analyze('SELECT *, a + 1 FROM t', [$table]);
        $rule = new ExpressionAffinity();
        $literal = new IntegerLiteral('1');

        self::assertSame($table->declarations()[0]->columns[0], $rule->step($query->field(0)));
        self::assertSame($query->field(1)->expression, $rule->step($query->field(1)));
        self::assertSame($literal, $rule->step($literal));
        self::assertSame($table->declarations()[0]->columns[0], $rule->step($table->declarations()[0]->columns[0]));
        self::assertNull($rule->step(null));
    }

    public function testFieldAnswersTheSourceOfAnExpandedStarOrOfTheExpression(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $query = $semantics->analyze('SELECT *, CAST(a AS TEXT), 1 FROM t', [$table]);
        $rule = new ExpressionAffinity();

        self::assertSame($table->declarations()[0]->columns[0], $rule->field($query->field(0), $query->facts));
        self::assertInstanceOf(Cast::class, $rule->field($query->field(1), $query->facts));
        self::assertNull($rule->field($query->field(2), $query->facts));
    }

    public function testAffinityIsThatOfTheDeclaredTypeOrOfTheCastTarget(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a VARCHAR(5), b)');
        $query = $semantics->analyze('SELECT CAST(a AS BLOB) FROM t', [$table]);
        $rule = new ExpressionAffinity();
        $cast = $query->field(0)->expression;

        self::assertInstanceOf(Cast::class, $cast);
        self::assertSame(Affinity::Text, $rule->affinity($table->declarations()[0]->columns[0]));
        self::assertSame(Affinity::Blob, $rule->affinity($table->declarations()[0]->columns[1]));
        self::assertSame(Affinity::Blob, $rule->affinity($cast));
        self::assertSame(Affinity::Numeric, $rule->affinity(new Cast(new IntegerLiteral('1'))));
        self::assertNull($rule->affinity(null));
        self::assertNull($rule->affinity(new Column(new \SqlSemantics\Statement\Identifier\Name('x'), \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Text)));
    }

    public function testOfIsTheAffinityOfAColumnOrACastAndNothingElse(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b INTEGER)');
        $query = $semantics->analyze('SELECT a, (b), CAST(a AS REAL), CAST(a AS NUMERIC), a || b, 1 FROM t', [$table]);
        $rule = new ExpressionAffinity();

        self::assertSame(Affinity::Text, $rule->of($query->field(0)->expression, $query->facts));
        self::assertSame(Affinity::Integer, $rule->of($query->field(1)->expression, $query->facts));
        self::assertSame(Affinity::Real, $rule->of($query->field(2)->expression, $query->facts));
        self::assertSame(Affinity::Numeric, $rule->of($query->field(3)->expression, $query->facts));
        self::assertNull($rule->of($query->field(4)->expression, $query->facts));
        self::assertNull($rule->of($query->field(5)->expression, $query->facts));
        self::assertNull($rule->of(null, $query->facts));
    }
}
