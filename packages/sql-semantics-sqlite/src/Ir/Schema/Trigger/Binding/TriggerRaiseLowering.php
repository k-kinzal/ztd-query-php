<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaise;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaisePolicy;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Raise;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn as ParsedResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTable;

/**
 * Resolves the control effect of a supported trigger query.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerRaiseLowering
{
    /**
     * Checks the source-free execution boundary and resolves the message and guard.
     *
     * @throws InvalidConstruction When the query has no checked interruption role
     */
    public function lower(BoundStatement $operation, TriggerTable $source, TableSource $watched, ParsedSelect $query): TriggerRaise
    {
        Check::input(
            count($query->columns) === 1 && $query->from === null && $query->groupBy === [] && $query->having === null
                && $query->windows === [] && $query->orderBy === [] && $query->limit === null,
            'A checked trigger RAISE needs one source-free projection without additional evaluation clauses.'
        );
        $column = $query->columns[0];
        $operands = new TriggerOperands();
        Check::input($column instanceof ParsedResultColumn, 'A checked trigger query needs one RAISE projection.');
        $expression = $operands->ungroup($column->expression);
        Check::input($expression instanceof Raise, 'A checked trigger query needs one RAISE projection.');
        $message = $expression->message === null ? null : $operands->value($operation, $source, $watched, $expression->message);
        $condition = $operands->condition($operation, $source, $watched, $query->where);

        return new TriggerRaise(TriggerRaisePolicy::from($expression->action->value), $message, $condition);
    }
}
