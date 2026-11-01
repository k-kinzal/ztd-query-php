<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Event;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Schedule;
use SqlSemantics\Statement\Node;

/**
 * Identifies event schedule dependencies outside unused CTE definitions.
 *
 * A reference to a CTE is itself a table dependency, even if its definition
 * only selects constants. A definition without a reference does not count.
 * Observed on MySQL 8.0.44, 8.4.7, 9.0.1 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Dependencies
{
    /**
     * Collects table uses and function calls in the schedule's active expressions.
     *
     * @return list<TableReference|ExplicitTable|FunctionCall>
     */
    public function sources(Schedule $schedule): array
    {
        $pending = [$schedule];
        $sources = [];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...$value);
            } elseif ($value instanceof Node && !$value instanceof CommonTableExpression) {
                if ($value instanceof TableReference || $value instanceof ExplicitTable || $value instanceof FunctionCall) {
                    $sources[] = $value;
                }
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
        return $sources;
    }

    /**
     * Names the unsupported dependency in the release's diagnostic vocabulary.
     */
    public static function feature(GrammarRelease $release): string
    {
        return in_array($release, [GrammarRelease::MySql901, GrammarRelease::MySql910], true)
            ? 'Event schedule expressions which contain subqueries or stored function calls'
            : 'Usage of subqueries or stored function calls as part of this statement';
    }
}
