<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch;
use SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Checks assignment scopes against the lexical variable declarations.
 *
 * A named local remains local even when SET carries a system scope keyword;
 * that combination has no language interpretation. Explicit @@ targets are
 * independent of lexical bindings. Observed on MySQL 5.7.44 and 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LocalAssignments
{
    /**
     * Finds an assignment whose system scope conflicts with its local binding.
     *
     * @param list<Name> $locals Names inherited from parameters or enclosing blocks
     */
    public function scoped(Node $body, array $locals = []): M\Utility\Set\NameAssignment|M\Routine\Program\DeferredAssignment|null
    {
        if ($body instanceof M\Routine\Program\Block) {
            foreach ($body->declarations as $declaration) {
                if ($declaration instanceof M\Routine\Program\VariableDeclaration) {
                    array_push($locals, ...$declaration->names);
                }
            }
        }
        if ($body instanceof M\Utility\Set\SetVariables || $body instanceof M\Routine\Program\DeferredAssignments) {
            $weights = new GeneralWeights();
            $keys = array_map(static fn (Name $name): string => $weights->key($name->value), $locals);
            foreach ($body->items as $item) {
                if (!$item instanceof M\Utility\Set\NameAssignment && !($item instanceof M\Routine\Program\DeferredAssignment && $item->target instanceof Name)) {
                    continue;
                }
                $name = $item instanceof M\Utility\Set\NameAssignment ? $item->name : $item->target;
                if ($item->scope !== null && $item->qualifier === null && in_array($weights->key($name->value), $keys, true)) {
                    return $item;
                }
            }
        }
        foreach ((new ReturnSearch())->nested($body) as $nested) {
            $found = $this->scoped($nested, $locals);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }
}
