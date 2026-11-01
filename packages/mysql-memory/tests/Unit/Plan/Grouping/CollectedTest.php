<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Grouping;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Grouping\Collected;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Collected::class)]
#[Medium]
final class CollectedTest extends TestCase
{
    public function testCompilePreparesAnAggregateBeforeTheFollowingRowOperand(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        $session->query('SELECT COUNT(*),(1,2)');
    }

    public function testCompilePreservesTheEarlierNameInTheSameExpression(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("Unknown column 'missing' in 'field list'");
        $session->query('SELECT missing+SUM(other),(1,2)');
    }
}
