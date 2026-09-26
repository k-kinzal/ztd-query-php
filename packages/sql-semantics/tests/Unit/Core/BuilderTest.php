<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Builder;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Writer;

#[CoversClass(Builder::class)]
#[CoversClass(\SqlSemantics\Core\Composition\Composition::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(CompositionException::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\LeafReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Composition\Operands::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Builder::class)]
#[Medium]
final class BuilderTest extends TestCase
{
    #[TestWith([MySqlDialect::MySql, "`select` = 'it''s' AND NOT( a OR b )"])]
    #[TestWith([PostgreSqlDialect::PostgreSql, "\"select\" = 'it''s' AND NOT( a OR b )"])]
    #[TestWith([SqliteDialect::Sqlite, "\"select\" = 'it''s' AND NOT( a OR b )"])]
    public function testAndOrNotAndCompareComposeTheSameConditionInEveryDialect(Dialect $dialect, string $expected): void
    {
        $semantics = new Semantics($dialect);
        $builder = $semantics->builder();
        $condition = $builder->and($builder->compare($builder->column('select'), '=', $builder->string("it's")), $builder->not($builder->or($builder->column('a'), $builder->column('b'))));
        self::assertSame($expected, Writer::render($condition));
        self::assertSame('SELECT ' . $expected, $semantics->analyze('SELECT ' . $expected)->toString());
    }

    /**
     * @return iterable<string, array{Dialect, int|float}>
     */
    public static function providerNumbers(): iterable
    {
        foreach ([MySqlDialect::MySql, PostgreSqlDialect::PostgreSql, SqliteDialect::Sqlite] as $dialect) {
            foreach ([42, -7, 0, PHP_INT_MAX, 1.5, -2.0, 0.1, 1e25] as $value) {
                yield $dialect->value . ' ' . var_export($value, true) => [$dialect, $value];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerNumbers')]
    public function testIntegerAndFloatRoundTripThroughAnalysis(Dialect $dialect, int|float $value): void
    {
        $semantics = new Semantics($dialect);
        $literal = is_int($value) ? $semantics->builder()->integer($value) : $semantics->builder()->float($value);
        $sql = 'SELECT ' . Writer::render($literal);
        self::assertSame($sql, $semantics->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{Dialect, string}>
     */
    public static function providerStrings(): iterable
    {
        foreach ([MySqlDialect::MySql, PostgreSqlDialect::PostgreSql, SqliteDialect::Sqlite] as $dialect) {
            foreach (['', "it's", "a\nb", 'back\\slash', '日本語', "\x01\xff"] as $value) {
                yield $dialect->value . ' ' . var_export($value, true) => [$dialect, $value];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerStrings')]
    public function testStringAndBinaryRoundTripThroughAnalysis(Dialect $dialect, string $value): void
    {
        $semantics = new Semantics($dialect);
        $sql = 'SELECT ' . Writer::render($semantics->builder()->string($value)) . ' , ' . Writer::render($semantics->builder()->binary($value));
        self::assertSame($sql, $semantics->analyze($sql)->toString());
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testBooleanAndNullRoundTripThroughAnalysis(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $builder = $semantics->builder();
        $sql = 'SELECT ' . Writer::render($builder->boolean(true)) . ' , ' . Writer::render($builder->boolean(false)) . ' , ' . Writer::render($builder->null());
        self::assertSame($sql, $semantics->analyze($sql)->toString());
        self::assertSame('SELECT TRUE , FALSE , NULL', $sql);
    }

    #[TestWith([MySqlDialect::MySql, 'db.t.c', 'db.t'])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'db.t.c', 'db.t'])]
    #[TestWith([SqliteDialect::Sqlite, 'db.t.c', 'db.t'])]
    public function testColumnAndTableAreQualifiedByTheirParts(Dialect $dialect, string $column, string $table): void
    {
        $builder = (new Semantics($dialect))->builder();
        self::assertSame($column, Writer::render($builder->column('db', 't', 'c')));
        self::assertSame($table, Writer::render($builder->table('db', 't')));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testTableIsQualifiedByItsSchema(Dialect $dialect): void
    {
        self::assertSame('s.t', Writer::render((new Semantics($dialect))->builder()->table('s', 't')));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testNullIsSpelledNull(Dialect $dialect): void
    {
        self::assertSame('NULL', Writer::render((new Semantics($dialect))->builder()->null()));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testBinaryIsAHexadecimalLiteral(Dialect $dialect): void
    {
        self::assertSame("X'00ff'", Writer::render((new Semantics($dialect))->builder()->binary("\x00\xff")));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testOrChainsToTheLeft(Dialect $dialect): void
    {
        $builder = (new Semantics($dialect))->builder();
        self::assertSame('a OR b OR c', Writer::render($builder->or($builder->or($builder->column('a'), $builder->column('b')), $builder->column('c'))));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testNotParenthesizesAConjunction(Dialect $dialect): void
    {
        $builder = (new Semantics($dialect))->builder();
        self::assertSame('NOT( a AND b )', Writer::render($builder->not($builder->and($builder->column('a'), $builder->column('b')))));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testCteNamesAQuery(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        self::assertSame('c AS( SELECT 1 )', Writer::render($semantics->builder()->cte('c', $semantics->analyze('SELECT 1')->command)));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testParenthesizedWrapsAnyExpression(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $builder = $semantics->builder();
        $sql = 'SELECT ' . Writer::render($builder->parenthesized($builder->and($builder->column('a'), $builder->column('b'))));
        self::assertSame('SELECT ( a AND b )', $sql);
        self::assertSame($sql, str_replace('SELECT(', 'SELECT (', $semantics->analyze($sql)->toString()));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testParameterRoundTripsThroughAnalysis(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $sql = 'SELECT ' . Writer::render($semantics->builder()->parameter());
        self::assertSame($sql, $semantics->analyze($sql)->toString());
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testIdentifierIsQuotedOnlyWhenNeeded(Dialect $dialect): void
    {
        $builder = (new Semantics($dialect))->builder();
        self::assertSame('users', Writer::render($builder->identifier('users')));
        self::assertStringContainsString('select', Writer::render($builder->identifier('select')));
        self::assertNotSame('select', Writer::render($builder->identifier('select')));
        self::assertNotSame('a b', Writer::render($builder->identifier('a b')));
        self::assertStringContainsString('a b', Writer::render($builder->identifier('a b')));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testIdentifierRejectsAnEmptyName(Dialect $dialect): void
    {
        $this->expectException(CompositionException::class);
        (new Semantics($dialect))->builder()->identifier('');
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testCompareRejectsAnUnknownOperator(Dialect $dialect): void
    {
        $builder = (new Semantics($dialect))->builder();
        $this->expectException(CompositionException::class);
        $builder->compare($builder->column('a'), '~', $builder->column('b'));
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testFloatRejectsANonFiniteNumber(Dialect $dialect): void
    {
        $this->expectException(CompositionException::class);
        (new Semantics($dialect))->builder()->float(NAN);
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testWithNeedsExpressions(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $this->expectException(CompositionException::class);
        $semantics->builder()->with([], $semantics->analyze('SELECT 1')->command);
    }

    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testUnionAllCteAndWithShadowATableInEveryDialect(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id, \'a\' AS name')->command, $semantics->analyze('SELECT 2, \'b\'')->command);
        $query = $builder->with([$builder->cte('users', $rows, ['id', 'name'])], $semantics->analyze('SELECT name FROM users WHERE id = 2 ORDER BY name')->command);
        $sql = Writer::render($query);
        self::assertStringStartsWith('WITH users', $sql);
        self::assertSame($sql, $semantics->analyze($sql)->toString());
        self::assertInstanceOf(\SqlSemantics\Statement\Command::class, $query);
    }
}
