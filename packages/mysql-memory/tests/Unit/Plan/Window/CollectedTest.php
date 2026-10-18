<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Window\Collected;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Collected::class)]
#[Medium]
final class CollectedTest extends TestCase
{
    public function testOptionsPrecedeARefusedFrameAfterNameResolution(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support '<window function>(DISTINCT ..)'");
        $session->query('SELECT SUM(DISTINCT 1) OVER (GROUPS CURRENT ROW)');
    }

    public function testCompilePreparesAWindowBeforeTheFollowingRowOperand(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        $session->query('SELECT FIRST_VALUE(1) IGNORE NULLS OVER (),(1,2)');
    }

    public function testCompilePreservesTheEarlierNameInTheSameExpression(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("Unknown column 'missing' in 'field list'");
        $session->query('SELECT missing+SUM(other) OVER (),(1,2)');
    }

    public function testNamedResolvesTheRetainedDefinitionBeforeTheRowArgument(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        $session->query('WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER W WINDOW w AS ()) SELECT 1 FROM c');
    }

    public function testNamedReportsAMissingDefinitionBeforeTheRowArgument(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(3579);
        $session->query('WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER w) SELECT 1 FROM c');
    }
    public function testFinishResolvesWindowOrderingBeforeAnUnsupportedOption(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("Unknown column 'missing' in 'window order by'");
        $session->query('SELECT FIRST_VALUE(1) IGNORE NULLS OVER (ORDER BY missing)');
    }

}
