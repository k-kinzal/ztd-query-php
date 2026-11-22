<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Bench;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Testing\MySql\Inspection;

/**
 * Measures the analysis of a statement: parsing, lowering, derivation and rendering with its checks.
 */
#[Bench\Revs(10)]
#[Bench\Iterations(3)]
final class StatementBench
{
    /**
     * Analyzes a grouped join with a window, ordering and limit on the latest release and writes it back.
     */
    public function benchSelect(): void
    {
        $semantics = new Inspection();
        $semantics->inspect("SELECT t.a, COUNT(*) AS n, 'label', 0x1F, @@session.sql_mode, ROW_NUMBER() OVER w FROM shop.t AS t LEFT JOIN u USING (id) WHERE t.b > 1 GROUP BY t.a WINDOW w AS (ORDER BY t.a) ORDER BY n DESC LIMIT 10");
    }

    /**
     * Analyzes the same projection on MySQL 5.7 under ANSI_QUOTES and writes it back.
     */
    public function benchLegacySelect(): void
    {
        $semantics = new Inspection('mysql-5.7.44', Mode::fromString('ANSI_QUOTES'));
        $semantics->inspect('SELECT t.a, "b" AS total, \'label\', ? FROM shop.t AS t WHERE t.a <=> NULL');
    }
}
