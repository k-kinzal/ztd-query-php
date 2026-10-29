<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Declaration;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\TypeLimits;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Statement\Node;

/**
 * Finds declared type sizes that no supported release can interpret.
 *
 * These are fixed operand constraints, independent of catalogs, row values or
 * execution. Statements keep their typed operands and publish these conditions;
 * stored definitions also check them in branches that never execute.
 * BIT(0) remains a legacy type: a release-specific refusal is not intrinsic.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class FixedSizeBounds
{
    /**
     * Finds the first fixed size contradiction in a semantic node graph.
     *
     * @param Node|list<Node> $root One structure or an ordered operand list
     */
    public function problem(Node|array $root): ?InvalidTypeSize
    {
        $pending = [$root];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (is_array($node)) {
                array_push($pending, ...array_reverse(array_values($node)));
            } elseif ($node instanceof ColumnDefinition) {
                $problem = $this->type($node->specification->dataType(), $node->name->column->value);
                if ($problem !== null) {
                    return $problem;
                }
                array_push($pending, ...array_reverse(array_values(get_object_vars($node))));
            } elseif ($node instanceof TypeName) {
                $problem = $this->type($node, '');
                if ($problem !== null) {
                    return $problem;
                }
            } elseif ($node instanceof Node) {
                array_push($pending, ...array_reverse(array_values(get_object_vars($node))));
            }
        }
        return null;
    }

    /**
     * Checks parameter, return and body declarations in their written order.
     */
    public function declaration(Node $body, ?ParameterList $parameters = null, ?DeclaredName $returns = null): ?InvalidTypeSize
    {
        return ($parameters === null ? null : $this->problem($parameters))
            ?? ($returns === null ? null : $this->type($returns, ''))
            ?? $this->problem($body);
    }

    /**
     * Distinguishes common size bounds from the legacy BIT(0) interpretation.
     */
    public function type(TypeName $type, string $name): ?InvalidTypeSize
    {
        $rules = new TypeLimits();
        $legacy = $rules->problem($type, $name, GrammarRelease::MySql5651);
        return $legacy !== null && $rules->problem($type, $name, GrammarRelease::MySql847) !== null ? $legacy : null;
    }
}
