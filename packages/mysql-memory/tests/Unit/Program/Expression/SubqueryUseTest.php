<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Program\Expression\SubqueryUse::class)]
#[Medium]
final class SubqueryUseTest extends TestCase
{
    public function testRaiseKeepsTheModernScalarQuantifierWidthAheadOfTheOuterName(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7'))->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        $session->query('SELECT missing = ALL(SELECT 1,2)');
    }

    public function testOperandUsesTheMembershipDiagnosticClause(): void
    {
        $session = (new \MySqlMemory\Instance('8.0.44'))->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("Unknown column 'missing' in 'IN/ALL/ANY subquery'");
        $session->query('SELECT missing = ALL(SELECT 1,2)');
    }
}
