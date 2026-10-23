<?php

declare(strict_types=1);

namespace Tests\Generator;

use MySqlMemory\Typing\Domain;
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
     * @return list<array{null, Domain}> Explicit NULL inputs, including their resolved types
     */
    public function nulls(Node $node): array
    {
        $values = [];
        $pending = [$node];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Parameter) {
                $values[] = [null, Domain::null()];
            } elseif ($value instanceof Node) {
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }

        return $values;
    }    /**
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
