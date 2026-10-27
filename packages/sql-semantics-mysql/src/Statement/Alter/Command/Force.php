<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * `FORCE`: a request to rebuild the table even when nothing else changes.
 *
 * Mirrors PT_alter_table_force.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 *
 * @visibility public
 * @example Rebuilding a table
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('alter table t force')->toString() // => 'ALTER TABLE t FORCE'
 */
final class Force implements AlterCommand
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('FORCE');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }
}
