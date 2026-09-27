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
use SqlSemantics\Platform\MySql\Builder;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(Builder::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Casts::class)]
#[UsesClass(Mode::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Queries::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\LegacyUnions::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Expressions::class)]
#[Medium]
final class BuilderTest extends TestCase
{
    #[TestWith(['users', 'users'])]
    #[TestWith(['action', 'action'])]
    #[TestWith(['select', '`select`'])]
    #[TestWith(['1e3', '`1e3`'])]
    #[TestWith(['a`b c', '`a``b c`'])]
    #[TestWith(['日本', '`日本`'])]
    public function testIdentifierIsBareOnlyWhenTheReleaseReadsItAsAName(string $name, string $expected): void
    {
        self::assertSame($expected, Writer::render((new Semantics(Dialect::MySql))->builder()->identifier($name)));
    }

    public function testColumnQualifiersReadAnyWordAfterTheDotAsAName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        self::assertSame('t.select', Writer::render($builder->column('t', 'select')));
        self::assertSame('t.`a b`', Writer::render($builder->column('t', 'a b')));
        self::assertSame('db.t.x', Writer::render($builder->column('db', 't', 'x')));
        self::assertSame('`select`', Writer::render($builder->column('select')));
        Composed::assertExpressionRoundTrips($semantics, $builder->column('t', 'action'));
        Composed::assertExpressionRoundTrips($semantics, $builder->column('db', 't', 'x'));
    }

    public function testTableIsQualifiedByItsSchema(): void
    {
        $builder = (new Semantics(Dialect::MySql))->builder();
        self::assertSame('db.from', Writer::render($builder->table('db', 'from')));
        self::assertSame('`select`', Writer::render($builder->table('select')));
        $this->expectException(CompositionException::class);
        $builder->table('a', 'b', 'c');
    }

    public function testStringIsEscapedAsTheModeReadsIt(): void
    {
        $plain = new Semantics(Dialect::MySql);
        $raw = new Semantics(Dialect::MySql, null, Mode::fromString('NO_BACKSLASH_ESCAPES'));
        self::assertSame("'a\\\\b''c\\0d'", Writer::render($plain->builder()->string("a\\b'c\0d")));
        self::assertSame("'a\\b''c\0d'", Writer::render($raw->builder()->string("a\\b'c\0d")));
        Composed::assertExpressionRoundTrips($plain, $plain->builder()->string("a\\b'c\0d"));
        Composed::assertExpressionRoundTrips($raw, $raw->builder()->string("a\\b'c\0d"));
    }

    #[TestWith([0, '0'])]
    #[TestWith([42, '42'])]
    #[TestWith([-5, '- 5'])]
    #[TestWith([PHP_INT_MAX, '9223372036854775807'])]
    public function testIntegerNegatesThroughTheUnaryMinus(int $value, string $expected): void
    {
        $semantics = new Semantics(Dialect::MySql);
        self::assertSame($expected, Writer::render($semantics->builder()->integer($value)));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->integer($value));
    }

    #[TestWith([0.1, '1e-1'])]
    #[TestWith([0.1 + 0.2, '3.0000000000000004e-1'])]
    #[TestWith([-2.0, '- 2e0'])]
    #[TestWith([-0.0, '- 0e0'])]
    #[TestWith([1e25, '1e25'])]
    #[TestWith([5e-324, '5e-324'])]
    public function testFloatIsAnApproximateValueLiteralOfExactlyTheNumber(float $value, string $expected): void
    {
        $semantics = new Semantics(Dialect::MySql);
        self::assertSame($expected, Writer::render($semantics->builder()->float($value)));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->float($value));
    }

    public function testBooleanIsTrueOrFalse(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        self::assertSame('TRUE', Writer::render($semantics->builder()->boolean(true)));
        self::assertSame('FALSE', Writer::render($semantics->builder()->boolean(false)));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->boolean(false));
    }

    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-5.6.51'])]
    public function testNullIsTheNullLiteralOfTheRelease(string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        self::assertSame('NULL', Writer::render($semantics->builder()->null()));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->null());
    }

    public function testBinaryIsAHexadecimalLiteral(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        self::assertSame("X'007f'", Writer::render($semantics->builder()->binary("\x00\x7f")));
        Composed::assertExpressionRoundTrips($semantics, $semantics->builder()->binary("\xff"));
    }

    public function testParameterIsTheQuestionMarkOrANamedPlaceholder(): void
    {
        self::assertSame('?', Writer::render((new Semantics(Dialect::MySql))->builder()->parameter(3)));
        $named = new Semantics(Dialect::MySql, parameters: Parameters::Named);
        self::assertSame('?', Writer::render($named->builder()->parameter()));
        self::assertSame(':user_id', Writer::render($named->builder()->parameter('user_id')));
        Composed::assertExpressionRoundTrips($named, $named->builder()->parameter('user_id'));
        $this->expectException(CompositionException::class);
        (new Semantics(Dialect::MySql))->builder()->parameter('user_id');
    }

    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-5.6.51'])]
    public function testAndParenthesizesWeakerOperandsInEveryRelease(string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $builder = $semantics->builder();
        self::assertSame('a AND b AND c', Writer::render($builder->and($builder->and($builder->column('a'), $builder->column('b')), $builder->column('c'))));
        self::assertSame('a AND( b AND c )', Writer::render($builder->and($builder->column('a'), $builder->and($builder->column('b'), $builder->column('c')))));
        self::assertSame('a AND( b OR c )', Writer::render($builder->and($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c')))));
        Composed::assertExpressionRoundTrips($semantics, $builder->and($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c'))));
    }

    public function testOrIsWrittenAsTheWordWhateverTheModeMakesOfPipes(): void
    {
        $semantics = new Semantics(Dialect::MySql, null, Mode::fromString('PIPES_AS_CONCAT'));
        $builder = $semantics->builder();
        self::assertSame('a OR b', Writer::render($builder->or($builder->column('a'), $builder->column('b'))));
        self::assertSame('a OR b OR c', Writer::render($builder->or($builder->or($builder->column('a'), $builder->column('b')), $builder->column('c'))));
        Composed::assertExpressionRoundTrips($semantics, $builder->or($builder->column('a'), $builder->column('b')));
    }

    public function testNotBindsAsTheModeSays(): void
    {
        $plain = new Semantics(Dialect::MySql);
        $high = new Semantics(Dialect::MySql, null, Mode::fromString('HIGH_NOT_PRECEDENCE'));
        $comparison = static fn (\SqlSemantics\Core\Builder $builder): Element => $builder->compare($builder->column('a'), '=', $builder->integer(1));
        self::assertSame('NOT a = 1', Writer::render($plain->builder()->not($comparison($plain->builder()))));
        self::assertSame('NOT( a = 1 )', Writer::render($high->builder()->not($comparison($high->builder()))));
        self::assertSame('NOT( a AND b )', Writer::render($plain->builder()->not($plain->builder()->and($plain->builder()->column('a'), $plain->builder()->column('b')))));
        Composed::assertExpressionRoundTrips($plain, $plain->builder()->not($comparison($plain->builder())));
        Composed::assertExpressionRoundTrips($high, $high->builder()->not($comparison($high->builder())));
        Composed::assertExpressionRoundTrips($high, $high->builder()->and($high->builder()->not($high->builder()->column('a')), $high->builder()->column('b')));
    }

    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-5.6.51'])]
    public function testCompareParenthesizesConditionsAndHasTheNullSafeEquality(string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $builder = $semantics->builder();
        self::assertSame('( a AND b ) = c', Writer::render($builder->compare($builder->and($builder->column('a'), $builder->column('b')), '=', $builder->column('c'))));
        self::assertSame('a <=> NULL', Writer::render($builder->compare($builder->column('a'), '<=>', $builder->null())));
        self::assertSame('a != b', Writer::render($builder->compare($builder->column('a'), '!=', $builder->column('b'))));
        Composed::assertExpressionRoundTrips($semantics, $builder->compare($builder->and($builder->column('a'), $builder->column('b')), '=', $builder->column('c')));
        Composed::assertExpressionRoundTrips($semantics, $builder->compare($builder->column('a'), '<=>', $builder->null()));
        $this->expectException(CompositionException::class);
        $builder->compare($builder->column('a'), '~', $builder->column('b'));
    }

    public function testUnionAllCteAndWithComposeQueries(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
        self::assertSame('WITH users ( id ) AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users', Writer::render($builder->with([$builder->cte('users', $rows, ['id'])], $semantics->analyze('SELECT id FROM users')->command)));
    }

    public function testCteNeedsRelease80(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $this->expectException(CompositionException::class);
        $semantics->builder()->cte('c', $semantics->analyze('SELECT 1')->command);
    }

    public function testWithNeedsRelease80(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $this->expectException(CompositionException::class);
        $semantics->builder()->with([$semantics->analyze('SELECT 1')->command], $semantics->analyze('SELECT 2')->command);
    }

    public function testParenthesizedWrapsAnExpression(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        self::assertSame('( a = 1 )', Writer::render($builder->parenthesized($builder->compare($builder->column('a'), '=', $builder->integer(1)))));
        Composed::assertExpressionRoundTrips($semantics, $builder->parenthesized($builder->column('a')));
    }

    public function testIsNullIsAnExpressionOfThisDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        $condition = $builder->isNull($builder->and($builder->column('a'), $builder->column('b')), true);
        self::assertSame('( a AND b ) IS NOT NULL', Writer::render($condition));
        Composed::assertExpressionRoundTrips($semantics, $condition);
    }

    public function testInIsAnExpressionOfThisDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        $membership = $builder->in($builder->column('a'), [$builder->integer(1), $builder->string('x')]);
        self::assertSame("a IN( 1 , 'x' )", Writer::render($membership));
        Composed::assertExpressionRoundTrips($semantics, $membership);
        $this->expectException(CompositionException::class);
        $builder->in($builder->column('a'), []);
    }

    public function testCaseIsAnExpressionOfThisDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        $case = $builder->case([[$builder->isNull($builder->column('a')), $builder->string('none')]], $builder->column('a'));
        self::assertSame("CASE WHEN a IS NULL THEN 'none' ELSE a END", Writer::render($case));
        Composed::assertExpressionRoundTrips($semantics, $case);
    }

    public function testCallWritesAKeywordFunctionInItsOwnForm(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        $call = $builder->call('coalesce', [$builder->column('a'), $builder->integer(0)]);
        self::assertSame('COALESCE( a , 0 )', Writer::render($call));
        Composed::assertExpressionRoundTrips($semantics, $call);
        self::assertSame('`My Func` ( a )', Writer::render($builder->call('My Func', [$builder->column('a')])));
        $this->expectException(CompositionException::class);
        $builder->call('row_number');
    }

    public function testCastIsAnExpressionOfThisDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        $cast = $builder->cast($builder->or($builder->column('a'), $builder->column('b')), new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::DoublePrecision));
        self::assertSame('CAST( a OR b AS DOUBLE )', Writer::render($cast));
        Composed::assertExpressionRoundTrips($semantics, $cast);
    }

    public function testSelectComposesShadowRowsWithoutSql(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        $type = new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::DoublePrecision);
        $rows = $builder->unionAll($builder->select([[$builder->cast($builder->integer(1), $type), 'id']]), $builder->select([[$builder->cast($builder->integer(2), $type), 'id']]));
        $empty = $builder->select([[$builder->cast($builder->null(), $type), 'id']], null, $builder->boolean(false));
        Composed::assertQueryRoundTrips($semantics, $rows);
        Composed::assertQueryRoundTrips($semantics, $empty);
        self::assertSame('SELECT CAST( NULL AS DOUBLE ) AS id WHERE FALSE', Writer::render($empty));
        $shadowed = $builder->with([$builder->cte('users', $rows)], $semantics->analyze('SELECT id FROM users')->command);
        Composed::assertQueryRoundTrips($semantics, $shadowed);
        self::assertSame('SELECT 1 AS `select` FROM app.users WHERE a', Writer::render($builder->select([[$builder->integer(1), 'select']], $builder->table('app', 'users'), $builder->column('a'))));
        $this->expectException(CompositionException::class);
        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->builder()->select([[$builder->integer(1), 'id']], null, $builder->boolean(false));
    }
}
