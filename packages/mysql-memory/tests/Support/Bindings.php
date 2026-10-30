<?php

declare(strict_types=1);

namespace Tests\Support;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Statement\Node;

/**
 * Supplies a defined input value for every parameter occurrence in a constructed model.
 * This fixture depends only on the structure, never on SQL diagnostics or execution results.
 */
final class Bindings
{
    /**
     * @return non-negative-int The number of marker occurrences requiring values
     */
    public function count(Node $node): int
    {
        $count = 0;
        $pending = [$node];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Parameter) {
                $count++;
            } elseif ($value instanceof Node) {
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }

        return $count;
    }

    /**
     * Selects the marker extension from the structure, before any execution.
     */
    public function parameters(Node $node): ParameterStyle
    {
        $pending = [$node];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (($value instanceof Parameter && $value->marker !== '?') || ($value instanceof LanguageProfile && $value->parameters === ParameterStyle::Named)) {
                return ParameterStyle::Named;
            } elseif ($value instanceof Node) {
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }

        return ParameterStyle::Native;
    }
}
