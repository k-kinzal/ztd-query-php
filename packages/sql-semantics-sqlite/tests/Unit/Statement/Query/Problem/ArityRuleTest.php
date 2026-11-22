<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ArityRule::class)]
#[Medium]
final class ArityRuleTest extends TestCase
{
    public function testCasesTellWhereTheCountsDisagree(): void
    {
        $semantics = new Inspection();
        $compound = $semantics->diagnose('SELECT 1 UNION SELECT 2, 3')->facts->diagnostics[0];
        $values = $semantics->diagnose('VALUES (1), (2, 3)')->facts->diagnostics[0];
        $common = $semantics->diagnose('WITH c(x, y) AS (SELECT 1) SELECT * FROM c')->facts->diagnostics[0];

        self::assertInstanceOf(ArityMismatch::class, $compound);
        self::assertSame(ArityRule::CompoundArms, $compound->rule);
        self::assertInstanceOf(ArityMismatch::class, $values);
        self::assertSame(ArityRule::ValueRows, $values->rule);
        self::assertInstanceOf(ArityMismatch::class, $common);
        self::assertSame(ArityRule::CommonTableColumns, $common->rule);
        self::assertCount(8, ArityRule::cases());
    }
}
