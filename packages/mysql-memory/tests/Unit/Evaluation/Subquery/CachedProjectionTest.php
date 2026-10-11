<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Subquery\CachedProjection;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachedProjection::class)]
#[Small]
final class CachedProjectionTest extends TestCase
{
    public function testDomainRetainsTheExpressionDomain(): void
    {
        $domain = Domain::integer();
        $value = new CachedProjection(new Constant($domain, null));

        self::assertSame($domain, $value->domain());
    }

    public function testEvaluateRetainsNullOnlyForTheCurrentStatement(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [null]);
        $value = new CachedProjection(new ColumnRead(Domain::integer(), 0));
        $first = $value->evaluate($frame);
        $frame->row = [20];
        $again = $value->evaluate($frame);
        $fresh = $value->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 1.0), [20]));

        self::assertSame([null, null, 20], [$first, $again, $fresh]);
    }

    public function testEvaluateKeepsConstantEmptyProjectionWarningsOnceInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query('SELECT (SELECT 1/0,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) FROM (SELECT 1 UNION ALL SELECT 2) t')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertCount(1, $warnings->rows);
    }
}
