<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Zoned;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Zoned::class)]
#[Small]
final class ZonedTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheResult(): void
    {
        $domain = new Domain(Kind::DateTime, Field::DateTime, 23, 3);

        self::assertSame($domain, (new Zoned(new Constant(Domain::null(), null), $domain))->domain());
    }

    public function testEvaluateDropsTheFractionOfTheTimestamp(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (x TIMESTAMP(3) NULL)');
        $session->query("INSERT INTO t VALUES ('2020-01-01 10:00:00.723'), (NULL)");
        $result = $session->query("SELECT CAST(x AT TIME ZONE 'UTC' AS DATETIME(6)), CAST(x AT TIME ZONE '+00:00' AS DATETIME) FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2020-01-01 10:00:00.000000', '2020-01-01 10:00:00'], [null, null]], $result->rows);
    }

    public function testEvaluateConvertsTheTimestampOfTheSessionZoneBackToUtc(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE z (ts TIMESTAMP(3))');
        $session->query("SET time_zone = '+05:00'");
        $session->query("INSERT INTO z VALUES ('2024-01-01 10:00:00.123')");

        $reply = $session->query("SELECT CAST(ts AT TIME ZONE 'UTC' AS DATETIME(3)) FROM z")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2024-01-01 05:00:00.000']], $reply->rows);
    }
}
