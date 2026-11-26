<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Facts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\ModifyingCommonTables::class)]
#[Medium]
final class ModifyingCommonTablesTest extends TestCase
{
    public function testCheckReportsAModifyingTableInASubquery(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->diagnose('SELECT * FROM (WITH x AS (DELETE FROM t) SELECT 1) AS s');
        self::assertSame('WITH clause containing a data-modifying statement must be at the top level', $query->facts->diagnostics[0]->message());
    }

    public function testTopFindsTheWithClauseInsideGroupingParentheses(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->diagnose('(WITH x AS (SELECT 1) SELECT 2)');
        self::assertNotNull((new \SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\ModifyingCommonTables())->top($query->statement));
    }
}
