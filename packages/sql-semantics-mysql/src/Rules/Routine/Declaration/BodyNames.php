<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch;
use SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignments;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Finds a bare SET target of a stored program body that names nothing.
 *
 * A bare name assigned in a body is a parameter or a variable declared in an
 * enclosing block, or else a system variable of the release; the server refuses
 * any other name as it reads the assignment (`CREATE PROCEDURE p() BEGIN SET x
 * = 1; SELECT * FROM; END` answers 1193 before the syntax error on 8.4.7). Which
 * names are system variables depends on the release, so this rule belongs to
 * the reading and to the generators rather than to the body's own invariants.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyNames
{
    /**
     * Answers the first unknown bare target, the SET statement holding it and the item written with it.
     *
     * @param list<Name> $locals Parameters and enclosing block variables
     * @return array{Name, Node, Node}|null
     */
    public function unknown(Node $body, array $locals, SystemVariables $catalog): ?array
    {
        if ($body instanceof Block) {
            foreach ($body->declarations as $declaration) {
                if ($declaration instanceof VariableDeclaration) {
                    array_push($locals, ...$declaration->names);
                } elseif ($declaration instanceof HandlerDeclaration) {
                    $problem = $this->unknown($declaration->statement, $locals, $catalog);
                    if ($problem !== null) {
                        return $problem;
                    }
                }
            }
        }
        if ($body instanceof SetVariables || $body instanceof DeferredAssignments) {
            $weights = new GeneralWeights();
            $keys = array_map(static fn (Name $name): string => $weights->key($name->value), $locals);
            foreach ($body->items as $item) {
                $name = match (true) {
                    $item instanceof NameAssignment && $item->scope === null && $item->qualifier === null => $item->name,
                    $item instanceof DeferredAssignment && $item->target instanceof Name => $item->target,
                    default => null,
                };
                if ($name !== null && !in_array($weights->key($name->value), $keys, true) && $catalog->find($name->value) === null) {
                    return [$name, $body, $item];
                }
            }
        }
        foreach ($body instanceof Block ? $body->statements : (new ReturnSearch())->nested($body) as $nested) {
            $problem = $this->unknown($nested, $locals, $catalog);
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }
}
