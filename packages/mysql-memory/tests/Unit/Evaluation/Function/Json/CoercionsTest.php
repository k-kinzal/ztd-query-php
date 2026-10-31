<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Json;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Function\Json\Coercions;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Coercions::class)]
#[Small]
final class CoercionsTest extends TestCase
{
    public function testToDoubleReadsNumbersAndWarnsOfOtherValues(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $domain = (new Domain(Kind::Json, Field::Json, 4294967295, 31, false, Collation::known('utf8mb4_bin')))->withSource('j');

        self::assertSame([1.5, 1.0, 100.0, 0.0], [Coercions::toDouble('1.5', $domain, $context), Coercions::toDouble('true', $domain, $context), Coercions::toDouble('"1e2"', $domain, $context), Coercions::toDouble('[1]', $domain, $context)]);
        self::assertSame(1, $session->diagnostics->count());
    }

    public function testToIntegerRoundsHalfToEvenAndClampsWithAWarning(): void
    {
        $result = (new Instance())->connect()->query("SELECT CAST(CAST('2.5' AS JSON) AS SIGNED), CAST(CAST('-1.5' AS JSON) AS SIGNED), CAST(CAST('\"9223372036854775808\"' AS JSON) AS SIGNED), CAST(CAST('1e30' AS JSON) AS SIGNED), CAST(CAST('18446744073709551615' AS JSON) AS SIGNED)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '-2', '-9223372036854775808', '9223372036854775807', '-1']], $result->rows);
        self::assertSame(1, $result->warnings);
    }

    public function testToDecimalReadsUnsignedIntegersAsSigned(): void
    {
        $result = (new Instance())->connect()->query("SELECT CAST(CAST('18446744073709551615' AS JSON) AS DECIMAL(10,2)), CAST(CAST('\" 12 \"' AS JSON) AS DECIMAL(10,2)), CAST(CAST('true' AS JSON) AS DECIMAL(3,1))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-1.00', '12.00', '1.0']], $result->rows);
    }

    public function testToTemporalReadsStringsAndTemporalValues(): void
    {
        $result = (new Instance())->connect()->query("SELECT CAST(CAST('\"2020-01-02\"' AS JSON) AS DATE), CAST(CAST(DATE'2020-01-02' AS JSON) AS DATE), CAST(CAST('[1]' AS JSON) AS DATE), CAST(CAST(TIMESTAMP'2020-01-02 10:00:00' AS JSON) AS TIME)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2020-01-02', '2020-01-02', null, null]], $result->rows);
        self::assertSame(2, $result->warnings);
    }

    public function testTextWarnsOfWhatFollowsTheNumber(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $domain = Domain::null()->withSource('cast_as_json');

        self::assertSame(['12', '12', '0'], [Coercions::text(' 12 ', '/\A[0-9]+/', true, 'DECIMAL', $domain, $context), Coercions::text('12 ', '/\A[0-9]+/', false, 'INTEGER', $domain, $context), Coercions::text('', '/\A[0-9]+/', false, 'INTEGER', $domain, $context)]);
        self::assertSame(2, $session->diagnostics->count());
    }

    public function testExactAppliesTheExponent(): void
    {
        self::assertSame(['150', '0.015', '12'], [Coercions::exact('1.5e2'), Coercions::exact('1.5e-2'), Coercions::exact('12.')]);
    }

    public function testRoundClampsBeyondTheRange(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame([2, -2, PHP_INT_MAX], [Coercions::round(2.5, Domain::null(), $context), Coercions::round(-2.5, Domain::null(), $context), Coercions::round(1e30, Domain::null(), $context)]);
        self::assertSame(1, $session->diagnostics->count());
    }

    public function testBoundedWrapsUnsignedIntegersAndClampsBeyond(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame([-1, PHP_INT_MIN, -1], [Coercions::bounded('18446744073709551615', Domain::null(), $context), Coercions::bounded('-9223372036854775809', Domain::null(), $context), Coercions::bounded('18446744073709551616', Domain::null(), $context)]);
        self::assertSame(2, $session->diagnostics->count());
    }

    public function testInvalidWarnsNamingTheSourceAndTheRow(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $context->row = 3;

        self::assertSame(0, Coercions::invalid('INTEGER', Domain::null()->withSource('j'), $context));
        $result = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Warning', '3156', 'Invalid JSON value for CAST to INTEGER from column j at row 3']], $result->rows);
    }
}
