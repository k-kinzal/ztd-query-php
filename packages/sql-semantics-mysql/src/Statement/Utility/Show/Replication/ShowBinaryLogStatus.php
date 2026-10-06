<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW BINARY LOG STATUS (SHOW MASTER STATUS): the position in the binary log of the source.
 *
 * Rule: MYSQL-SHOW-BINARY-LOG-STATUS-001. The two spellings are kept because each release accepts only
 * some of them (SHOW MASTER STATUS up to 8.3, SHOW BINARY LOG STATUS from
 * 8.2); they return the same columns, those of the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-binary-log-status.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW BINARY LOG STATUS');
 *     [$show->field(0)->name?->value, $show->toString()] // => ['File', 'SHOW BINARY LOG STATUS']
 */
final class ShowBinaryLogStatus implements Statement
{
    use Snapshot;

    /**
     * @param Terminology $terminology Whether MASTER STATUS or BINARY LOG STATUS is written
     */
    public function __construct(public readonly Terminology $terminology)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::LogStatus);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        match ($this->terminology) {
            Terminology::Legacy => $out->keyword('SHOW', 'MASTER', 'STATUS'),
            Terminology::Current => $out->keyword('SHOW', 'BINARY', 'LOG', 'STATUS'),
        };
    }
}
