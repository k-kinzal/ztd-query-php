<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Union;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Union\Widths;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Widths::class)]
#[Small]
final class WidthsTest extends TestCase
{
    public function testDifferChecksResolvedCteColumnsBeforeAllocatingASetField(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(1)');
        $this->expectExceptionCode(1222);
        $session->query('WITH c AS(SELECT 1,2) DELETE FROM t WHERE (SELECT * FROM c UNION SELECT 1)');
    }

    public function testDifferKeepsEarlierSetOperandPreparationErrors(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $this->expectExceptionMessage("Unknown column 'missing' in 'field list'");
        $session->query('WITH c AS(SELECT 1,2) DELETE FROM t WHERE (SELECT *,missing FROM c UNION SELECT 1)');
    }
}
