<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A relation action without an operand.
 *
 * One `AlterTableCmd` without operands; see `TableActionKind`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class TableAction implements AlterCommand
{
    use Snapshot;

    /**
     * @param TableActionKind $kind The action
     */
    public function __construct(public readonly TableActionKind $kind)
    {
    }

    /**
     * Derives nothing: the action has no operand.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword(...$this->kind->keywords());
    }
}
