<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Negation;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Negation::class)]
#[Small]
final class NegationTest extends TestCase
{
    public function testEvaluateNegatesTheTruthOfNumbers(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT NOT 0, NOT 1, NOT 0.0, NOT 0.1, NOT -2, NOT 0e0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '0', '0', '1']], $result->rows);
        self::assertSame(Field::LongLong, $result->columns[0]->type);
    }

    public function testEvaluateReturnsNullForNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT NOT NULL')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testEvaluateWarnsForAStringThatIsNoNumber(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT NOT 'abc', NOT '1'")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'abc'"]], $warnings->rows);
    }

    public function testEvaluateNegatesAColumnForEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (0), (3), (NULL)');
        $result = $session->query('SELECT a, NOT a FROM t ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null], ['0', '1'], ['3', '0']], $result->rows);
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $domain = Domain::integer();
        $negation = new Negation(new Constant(Domain::integer(), 1), $domain);

        self::assertSame($domain, $negation->domain());
    }
}
