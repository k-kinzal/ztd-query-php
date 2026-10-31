<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Casts;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Casts::class)]
#[Small]
final class CastsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Type\Resolved\Field}>
     */
    public static function providerRealModes(): iterable
    {
        yield 'double' => ['', M\Type\Resolved\Field::Double];
        yield 'float' => ['REAL_AS_FLOAT', M\Type\Resolved\Field::Float];
    }

    #[DataProvider('providerRealModes')]
    public function testCastResolvesTheGeneratedTypeChoiceFromTheExecutionMode(string $mode, M\Type\Resolved\Field $expected): void
    {
        $session = (new Instance('8.4.7', ['sql_mode' => $mode]))->connect();
        $number = new M\Literal\NumberLiteral('7', new ScalarFact(new Known(M\Type\Resolved\Domain::integer()), Nullability::NotNull));
        $types = new Choice([new M\Type\Floating(M\Type\Kind\FloatingKind::Float), new M\Type\Floating(M\Type\Kind\FloatingKind::Double)]);
        $cast = new M\Expression\Conversion\Cast($number, new M\Type\CastTarget(M\Type\Kind\CastKind::Real), scalarInformation: new ScalarFact($types, Nullability::NotNull));
        $name = new \SqlSemantics\Statement\Identifier\Name('value');
        $field = new \SqlSemantics\Statement\Shape\Field(0, new \SqlSemantics\Statement\Shape\OutputSlot($name, $types, Nullability::NotNull), $cast);
        $output = new \SqlSemantics\Statement\Fact\QueryFact([$field], \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive);
        $statement = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression($cast, $name)], queryInformation: $output), new M\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), output: $output));

        $result = (new \MySqlMemory\Session\Execution($session))->statement($statement);

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
        self::assertSame($expected, $result->columns[0]->type);
    }

    public function testTimestampResolvesTheInputTypeBeforeTheDeferredZoneCondition(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a DATETIME)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3998);
        $session->query("SELECT CAST(a AT TIME ZONE 'Etc/UTC' AS DATETIME) FROM t");
    }

    public function testCastCutsAStringToTheLengthOfTheTargetAndWarns(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CAST(12.5 AS CHAR(2))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['12']], $result->rows);
        self::assertSame([['Warning', 1292, "Truncated incorrect CHAR(2) value: '12.5'"]], $session->diagnostics->conditions);
    }

    public function testCastConvertsToTheNumericTargets(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('12abc' AS SIGNED), CAST(1.567 AS DECIMAL(4,2)), CAST(-1 AS UNSIGNED)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['12', '1.57', '18446744073709551615']], $result->rows);
        self::assertSame(['Warning', 1292, "Truncated incorrect INTEGER value: '12abc'"], $session->diagnostics->conditions[0]);
    }

    public function testCastConvertsToTheTemporalTargets(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('2024-02-29' AS DATE), CAST(TIMESTAMP '2024-02-29 10:11:12' AS TIME), CAST(20240229 AS DATETIME)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-02-29', '10:11:12', '2024-02-29 00:00:00']], $result->rows);
    }

    public function testCastGivesTheResultTheTypeOfTheTarget(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CAST(1.567 AS DECIMAL(4,2))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['NewDecimal', 2], [$result->columns[0]->type->name, $result->columns[0]->decimals]);
    }

    public function testAtTimeZoneRefusesAValueThatIsNoTimestampAndAZoneThatIsNotUtc(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (x TIMESTAMP NULL, y DATETIME)');
        $value = $session->run("SELECT CAST(y AT TIME ZONE 'x' AS DATETIME) FROM t");
        $zone = $session->run("SELECT CAST(x AT TIME ZONE '+01:00' AS DATETIME) FROM t");
        $null = $session->query("SELECT CAST(NULL AT TIME ZONE 'UTC' AS DATETIME)")[0];

        self::assertInstanceOf(SqlError::class, $value[0]);
        self::assertSame([3998, 'Cannot cast value to TIMESTAMP WITH TIME ZONE.'], [$value[0]->getCode(), $value[0]->getMessage()]);
        self::assertInstanceOf(SqlError::class, $zone[0]);
        self::assertSame([1298, "Unknown or incorrect time zone: '+01:00'"], [$zone[0]->getCode(), $zone[0]->getMessage()]);
        self::assertInstanceOf(ResultSet::class, $null);
        self::assertSame([[null]], $null->rows);
    }

    public function testLabelDistinguishesProjectedAndNestedConversions(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT CAST('123' AS DECIMAL(1)) AS value, CAST('123' AS DECIMAL(1))+1 AS nested");
        self::assertSame([
            ['Warning', 1264, "Out of range value for column 'value' at row 1"],
            ['Warning', 1264, "Out of range value for column '(null)' at row 1"],
        ], $session->diagnostics->conditions);
    }

    public function testLabelReadsAnArgumentsSpellingUnderTheResolvedClientCharset(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET NAMES utf8mb4');
        $session->query("SELECT ABS(cast('123' /*あ*/ as decimal(1))) AS result");
        self::assertSame([
            ['Warning', 1264, "Out of range value for column 'cast('123' /*あ*/ as decimal(1))' at row 1"],
        ], $session->diagnostics->conditions);
    }

}
