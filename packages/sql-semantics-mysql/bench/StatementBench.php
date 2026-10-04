<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Bench;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;

/**
 * Measures the analysis of a statement: parsing, lowering, derivation and rendering with its checks.
 */
#[Bench\Revs(10)]
#[Bench\Iterations(3)]
final class StatementBench
{
    /**
     * Analyzes a filtered projection of qualified columns and literals on the latest release and writes it back.
     */
    public function benchSelect(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $semantics->analyze("SELECT t.a, t.b AS total, 'label', 0x1F, @@session.sql_mode FROM shop.t AS t WHERE t.a = 1")->toString();
    }

    /**
     * Analyzes the same projection on MySQL 5.7 under ANSI_QUOTES and writes it back.
     */
    public function benchLegacySelect(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44', Mode::fromString('ANSI_QUOTES'));
        $semantics->analyze('SELECT t.a, "b" AS total, \'label\', ? FROM shop.t AS t WHERE t.a <=> NULL')->toString();
    }
}
