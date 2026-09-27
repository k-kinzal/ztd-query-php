<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Builder;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(Builder::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Expressions::class)]
#[Medium]
final class BuilderTest extends TestCase
{
    #[TestWith(['users', 'users'])]
    #[TestWith(['Users', 'Users'])]
    #[TestWith(['key', '"key"'])]
    #[TestWith(['select', '"select"'])]
    #[TestWith(['a"b c', '"a""b c"'])]
    public function testIdentifierQuotesEveryKeywordBecauseFallbackDependsOnPosition(string $name, string $expected): void
    {
        self::assertSame($expected, Writer::render((new Semantics(Dialect::Sqlite))->builder()->identifier($name)));
    }

    public function testColumnIsQualifiedByUpToTwoNames(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $builder = $semantics->builder();
        self::assertSame('"key"', Writer::render($builder->column('key')));
        self::assertSame('t.Name', Writer::render($builder->column('t', 'Name')));
        self::assertSame('s.t.x', Writer::render($builder->column('s', 't', 'x')));
        Composed::assertExpressionRoundTrips($semantics, $builder->column('s', 't', 'x'));
        $this->expectException(CompositionException::class);
        $builder->column('a', 'b', 'c', 'd');
    }

    public function testTableIsQualifiedByItsSchema(): void
    {
        $builder = (new Semantics(Dialect::Sqlite))->builder();
        self::assertSame('main."order"', Writer::render($builder->table('main', 'order')));
        self::assertSame('users', Writer::render($builder->table('users')));
    }

    public function testStringDoublesQuotesAndCannotHoldNul(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        self::assertSame("'a''b\\c'", Writer::render($semantics->builder()->string("a'b\\c")));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->string("a'b\\c"));
        $this->expectException(CompositionException::class);
        $semantics->builder()->string("a\0b");
    }

    #[TestWith([42, '42'])]
    #[TestWith([-5, '- 5'])]
    public function testIntegerNegatesThroughTheUnaryMinus(int $value, string $expected): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        self::assertSame($expected, Writer::render($semantics->builder()->integer($value)));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->integer($value));
    }

    #[TestWith([0.5, '0.5'])]
    #[TestWith([-2.0, '- 2.0'])]
    public function testFloatIsReadAsAFloat(float $value, string $expected): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        self::assertSame($expected, Writer::render($semantics->builder()->float($value)));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->float($value));
    }

    public function testBooleanIsTheTrueOrFalseName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        self::assertSame('TRUE', Writer::render($semantics->builder()->boolean(true)));
        self::assertSame('FALSE', Writer::render($semantics->builder()->boolean(false)));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->boolean(true));
    }

    public function testNullIsTheNullTerm(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        self::assertSame('NULL', Writer::render($semantics->builder()->null()));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->null());
    }

    public function testBinaryIsABlobLiteral(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        self::assertSame("X'01ff'", Writer::render($semantics->builder()->binary("\x01\xff")));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->binary("\x01\xff"));
    }

    public function testParameterFollowsTheParameterSyntax(): void
    {
        $native = new Semantics(Dialect::Sqlite);
        $pdo = new Semantics(Dialect::Sqlite, parameters: Parameters::Pdo);
        self::assertSame('?3', Writer::render($native->builder()->parameter(3)));
        self::assertSame('?', Writer::render($pdo->builder()->parameter(3)));
        Composed::assertExpressionRoundTrips($native, $native->builder()->parameter(3));
        $this->expectException(CompositionException::class);
        $native->builder()->parameter(0);
    }

    public function testAndParenthesizesWeakerOperands(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $builder = $semantics->builder();
        self::assertSame('a AND b AND c', Writer::render($builder->and($builder->and($builder->column('a'), $builder->column('b')), $builder->column('c'))));
        self::assertSame('a AND( b OR c )', Writer::render($builder->and($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c')))));
        Composed::assertExpressionRoundTrips($semantics, $builder->and($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c'))));
    }

    public function testOrChainsToTheLeft(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $builder = $semantics->builder();
        self::assertSame('a OR b OR c', Writer::render($builder->or($builder->or($builder->column('a'), $builder->column('b')), $builder->column('c'))));
        Composed::assertExpressionRoundTrips($semantics, $builder->or($builder->boolean(true), $builder->or($builder->null(), $builder->binary("\x01\xff"))));
    }

    public function testNotParenthesizesAWeakerOperand(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $builder = $semantics->builder();
        self::assertSame('NOT( a AND b )', Writer::render($builder->not($builder->and($builder->column('a'), $builder->column('b')))));
        self::assertSame('NOT a = 1', Writer::render($builder->not($builder->compare($builder->column('a'), '=', $builder->integer(1)))));
        Composed::assertExpressionRoundTrips($semantics, $builder->not($builder->and($builder->column('a'), $builder->column('b'))));
        Composed::assertExpressionRoundTrips($semantics, $builder->not($builder->compare($builder->column('a'), '=', $builder->integer(1))));
    }

    public function testCompareSpellsEverySqliteOperator(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $builder = $semantics->builder();
        self::assertSame('( a AND b ) = c', Writer::render($builder->compare($builder->and($builder->column('a'), $builder->column('b')), '=', $builder->column('c'))));
        self::assertSame('a == b', Writer::render($builder->compare($builder->column('a'), '==', $builder->column('b'))));
        self::assertSame('a != b', Writer::render($builder->compare($builder->column('a'), '!=', $builder->column('b'))));
        self::assertSame('a >= b', Writer::render($builder->compare($builder->column('a'), '>=', $builder->column('b'))));
        Composed::assertExpressionRoundTrips($semantics, $builder->compare($builder->and($builder->column('a'), $builder->column('b')), '=', $builder->column('c')));
        Composed::assertExpressionRoundTrips($semantics, $builder->compare($builder->column('a'), '<>', $builder->float(0.5)));
        $this->expectException(CompositionException::class);
        $builder->compare($builder->column('a'), '~', $builder->column('b'));
    }

    public function testParenthesizedWrapsAnExpression(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $builder = $semantics->builder();
        self::assertSame('( a = 1 )', Writer::render($builder->parenthesized($builder->compare($builder->column('a'), '=', $builder->integer(1)))));
        Composed::assertExpressionRoundTrips($semantics, $builder->parenthesized($builder->column('a')));
    }

    public function testUnionAllTakesOneSelectOnTheRight(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2;')->command);
        self::assertSame('SELECT 1 AS id UNION ALL SELECT 2', Writer::render($rows));
        $three = $builder->unionAll($rows, $semantics->analyze('SELECT 3 ORDER BY 1')->command);
        self::assertSame('SELECT 1 AS id UNION ALL SELECT 2 UNION ALL SELECT 3 ORDER BY 1', Writer::render($three));
        self::assertSame(Writer::render($three), $semantics->analyze(Writer::render($three))->toString());
        Composed::assertQueryRoundTrips($semantics, $rows);
        $this->expectException(CompositionException::class);
        $builder->unionAll($semantics->analyze('SELECT 0')->command, $rows);
    }

    public function testCteNamesASelectWithOptionalColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $builder = $semantics->builder();
        self::assertSame('users ( id , n ) AS( SELECT 1 AS id , 2 AS n )', Writer::render($builder->cte('users', $semantics->analyze('SELECT 1 AS id, 2 AS n')->command, ['id', 'n'])));
        self::assertSame('"key" AS( SELECT 1 )', Writer::render($builder->cte('key', $semantics->analyze('SELECT 1')->command)));
        $sql = 'WITH ' . Writer::render($builder->cte('users', $semantics->analyze('SELECT 1 AS id, 2 AS n')->command, ['id', 'n'])) . ' SELECT 1';
        self::assertSame($sql, $semantics->analyze($sql)->toString());
        $this->expectException(CompositionException::class);
        $builder->cte('c', $semantics->analyze('DELETE FROM t')->command);
    }

    public function testWithPrependsExpressionsAndKeepsExistingOnes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
        $query = $builder->with([$builder->cte('users', $rows, ['id'])], $semantics->analyze('SELECT id FROM users')->command);
        self::assertSame('WITH users ( id ) AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users', Writer::render($query));
        self::assertSame(Writer::render($query), $semantics->analyze(Writer::render($query))->toString());
        Composed::assertQueryRoundTrips($semantics, $query);
        $merged = $builder->with([$builder->cte('v', $rows)], $semantics->analyze('WITH RECURSIVE w AS (SELECT 3) SELECT id FROM users ORDER BY id LIMIT 1')->command);
        self::assertSame('WITH RECURSIVE v AS( SELECT 1 AS id UNION ALL SELECT 2 ) , w AS( SELECT 3 ) SELECT id FROM users ORDER BY id LIMIT 1', Writer::render($merged));
        self::assertSame(Writer::render($merged), $semantics->analyze(Writer::render($merged))->toString());
        $this->expectException(CompositionException::class);
        $builder->with([], $semantics->analyze('SELECT 1')->command);
    }
}
