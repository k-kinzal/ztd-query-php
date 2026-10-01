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
use SqlSemantics\Platform\PostgreSql\Builder;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(Builder::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Casts::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Queries::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Expressions::class)]
#[Medium]
final class BuilderTest extends TestCase
{
    #[TestWith(['users', 'users'])]
    #[TestWith(['Users', '"Users"'])]
    #[TestWith(['user', '"user"'])]
    #[TestWith(['select', '"select"'])]
    #[TestWith(['action', 'action'])]
    #[TestWith(['a"b c', '"a""b c"'])]
    public function testIdentifierIsQuotedWhenFoldingOrKeywordsWouldChangeIt(string $name, string $expected): void
    {
        self::assertSame($expected, Writer::render((new Semantics(Dialect::PostgreSql))->builder()->identifier($name)));
    }

    public function testColumnIsQualifiedByAnyNumberOfNames(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        self::assertSame('"user".name', Writer::render($builder->column('user', 'name')));
        self::assertSame('s.t."User"', Writer::render($builder->column('s', 't', 'User')));
        Composed::assertExpressionRoundTrips($semantics, $builder->column('s', 't', 'User'));
        $this->expectException(CompositionException::class);
        $builder->column();
    }

    public function testTableIsQualifiedByItsSchema(): void
    {
        $builder = (new Semantics(Dialect::PostgreSql))->builder();
        self::assertSame('public.order', Writer::render($builder->table('public', 'order')));
        self::assertSame('"Users"', Writer::render($builder->table('Users')));
    }

    public function testStringIsStandardConformingAndCannotHoldNul(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame("'a''b\\c'", Writer::render($semantics->builder()->string("a'b\\c")));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->string("a'b\\c"));
        $this->expectException(CompositionException::class);
        $semantics->builder()->string("a\0b");
    }

    #[TestWith([42, '42'])]
    #[TestWith([-5, '- 5'])]
    #[TestWith([PHP_INT_MAX, '9223372036854775807'])]
    public function testIntegerNegatesThroughTheUnaryMinus(int $value, string $expected): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame($expected, Writer::render($semantics->builder()->integer($value)));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->integer($value));
    }

    #[TestWith([0.5, '0.5'])]
    #[TestWith([0.1 + 0.2, '0.30000000000000004'])]
    #[TestWith([-2.0, '- 2.0'])]
    #[TestWith([-0.0, '- 0.0'])]
    #[TestWith([1e25, '1e25'])]
    public function testFloatIsReadAsANumeric(float $value, string $expected): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame($expected, Writer::render($semantics->builder()->float($value)));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->float($value));
    }

    public function testBooleanIsTrueOrFalse(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame('TRUE', Writer::render($semantics->builder()->boolean(true)));
        self::assertSame('FALSE', Writer::render($semantics->builder()->boolean(false)));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->boolean(true));
    }

    public function testNullIsTheNullConstant(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame('NULL', Writer::render($semantics->builder()->null()));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->null());
    }

    public function testBinaryIsAHexadecimalBitString(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame("X'ff'", Writer::render($semantics->builder()->binary("\xff")));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->binary("\xff"));
    }

    public function testParameterFollowsTheParameterSyntax(): void
    {
        $native = new Semantics(Dialect::PostgreSql);
        $named = new Semantics(Dialect::PostgreSql, parameters: Parameters::Named);
        self::assertSame('$3', Writer::render($native->builder()->parameter(3)));
        self::assertSame('$3', Writer::render($named->builder()->parameter(3)));
        self::assertSame(':user_id', Writer::render($named->builder()->parameter('user_id')));
        Composed::assertExpressionRoundTrips($native, $native->builder()->parameter(3));
        Composed::assertExpressionRoundTrips($named, $named->builder()->parameter('user_id'));
        $this->expectException(CompositionException::class);
        $native->builder()->parameter('user_id');
    }

    public function testAndParenthesizesWeakerOperands(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        self::assertSame('a AND b AND c', Writer::render($builder->and($builder->and($builder->column('a'), $builder->column('b')), $builder->column('c'))));
        self::assertSame('a AND( b OR c )', Writer::render($builder->and($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c')))));
        Composed::assertExpressionRoundTrips($semantics, $builder->and($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c'))));
    }

    public function testOrChainsToTheLeft(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        self::assertSame('a OR b OR c', Writer::render($builder->or($builder->or($builder->column('a'), $builder->column('b')), $builder->column('c'))));
        self::assertSame('a OR( b OR c )', Writer::render($builder->or($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c')))));
        Composed::assertExpressionRoundTrips($semantics, $builder->or($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c'))));
    }

    public function testNotBindsTighterThanAndAndLooserThanComparison(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        self::assertSame('NOT a = 1', Writer::render($builder->not($builder->compare($builder->column('a'), '=', $builder->integer(1)))));
        self::assertSame('NOT NOT a', Writer::render($builder->not($builder->not($builder->column('a')))));
        self::assertSame('NOT( a AND b )', Writer::render($builder->not($builder->and($builder->column('a'), $builder->column('b')))));
        Composed::assertExpressionRoundTrips($semantics, $builder->not($builder->and($builder->column('a'), $builder->column('b'))));
        Composed::assertExpressionRoundTrips($semantics, $builder->not($builder->compare($builder->column('a'), '=', $builder->integer(1))));
    }

    public function testCompareIsNonAssociativeAndSpellsBothInequalities(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        self::assertSame('( a = b ) = c', Writer::render($builder->compare($builder->compare($builder->column('a'), '=', $builder->column('b')), '=', $builder->column('c'))));
        self::assertSame('( a AND b ) = c', Writer::render($builder->compare($builder->and($builder->column('a'), $builder->column('b')), '=', $builder->column('c'))));
        self::assertSame('a != b', Writer::render($builder->compare($builder->column('a'), '!=', $builder->column('b'))));
        self::assertSame('a <> b', Writer::render($builder->compare($builder->column('a'), '<>', $builder->column('b'))));
        Composed::assertExpressionRoundTrips($semantics, $builder->compare($builder->compare($builder->column('a'), '=', $builder->column('b')), '>=', $builder->column('c')));
        Composed::assertExpressionRoundTrips($semantics, $builder->compare($builder->column('a'), '!=', $builder->column('b')));
        $this->expectException(CompositionException::class);
        $builder->compare($builder->column('a'), '~', $builder->column('b'));
    }

    public function testUnionAllCteAndWithComposeQueries(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
        self::assertSame('WITH users ( id ) AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users', Writer::render($builder->with([$builder->cte('users', $rows, ['id'])], $semantics->analyze('SELECT id FROM users')->command)));
    }

    public function testCteOverAnythingButAPreparableStatementIsRejected(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $this->expectException(CompositionException::class);
        $semantics->builder()->cte('c', $semantics->analyze('CREATE TABLE t (id int)')->command);
    }

    public function testWithNeedsExpressions(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $this->expectException(CompositionException::class);
        $semantics->builder()->with([], $semantics->analyze('SELECT 1')->command);
    }

    public function testParenthesizedWrapsAnExpression(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        self::assertSame('( a = 1 )', Writer::render($builder->parenthesized($builder->compare($builder->column('a'), '=', $builder->integer(1)))));
        Composed::assertExpressionRoundTrips($semantics, $builder->parenthesized($builder->column('a')));
    }

    public function testIsNullIsAnExpressionOfThisDatabase(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        $condition = $builder->isNull($builder->and($builder->column('a'), $builder->column('b')), true);
        self::assertSame('( a AND b ) IS NOT NULL', Writer::render($condition));
        Composed::assertExpressionRoundTrips($semantics, $condition);
    }

    public function testInIsAnExpressionOfThisDatabase(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        $membership = $builder->in($builder->column('a'), [$builder->integer(1), $builder->string('x')]);
        self::assertSame("a IN( 1 , 'x' )", Writer::render($membership));
        Composed::assertExpressionRoundTrips($semantics, $membership);
        $this->expectException(CompositionException::class);
        $builder->in($builder->column('a'), []);
    }

    public function testCaseIsAnExpressionOfThisDatabase(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        $case = $builder->case([[$builder->isNull($builder->column('a')), $builder->string('none')]], $builder->column('a'));
        self::assertSame("CASE WHEN a IS NULL THEN 'none' ELSE a END", Writer::render($case));
        Composed::assertExpressionRoundTrips($semantics, $case);
    }

    public function testCallWritesAKeywordFunctionInItsOwnForm(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        $call = $builder->call('coalesce', [$builder->column('a'), $builder->integer(0)]);
        self::assertSame('COALESCE( a , 0 )', Writer::render($call));
        Composed::assertExpressionRoundTrips($semantics, $call);
        self::assertSame('"My Func" ( a )', Writer::render($builder->call('My Func', [$builder->column('a')])));
        self::assertSame('"COALESCE" ( a )', Writer::render($builder->call('COALESCE', [$builder->column('a')])));
    }

    public function testCastIsAnExpressionOfThisDatabase(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        $cast = $builder->cast($builder->or($builder->column('a'), $builder->column('b')), new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::DoublePrecision));
        self::assertSame('CAST( a OR b AS DOUBLE PRECISION )', Writer::render($cast));
        Composed::assertExpressionRoundTrips($semantics, $cast);
    }

    public function testSelectComposesShadowRowsWithoutSql(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        $type = new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::DoublePrecision);
        $rows = $builder->unionAll($builder->select([[$builder->cast($builder->integer(1), $type), 'id']]), $builder->select([[$builder->cast($builder->integer(2), $type), 'id']]));
        $empty = $builder->select([[$builder->cast($builder->null(), $type), 'id']], null, $builder->boolean(false));
        Composed::assertQueryRoundTrips($semantics, $rows);
        Composed::assertQueryRoundTrips($semantics, $empty);
        self::assertSame('SELECT CAST( NULL AS DOUBLE PRECISION ) AS id WHERE FALSE', Writer::render($empty));
        $shadowed = $builder->with([$builder->cte('users', $rows)], $semantics->analyze('SELECT id FROM users')->command);
        Composed::assertQueryRoundTrips($semantics, $shadowed);
        self::assertSame('SELECT 1 AS select FROM app.users WHERE a', Writer::render($builder->select([[$builder->integer(1), 'select']], $builder->table('app', 'users'), $builder->column('a'))));
    }
}
