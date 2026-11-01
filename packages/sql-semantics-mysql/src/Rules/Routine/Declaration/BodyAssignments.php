<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch;
use SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Resolves the autocommit assignment restriction in function and trigger bodies.
 *
 * A routine parameter or block variable can shadow the unqualified name.
 * Explicit @@ targets always denote the system variable. Password assignments
 * carry the same body restriction. No values or database state are evaluated.
 * Observed through SQL on MySQL 5.7.44 and 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyAssignments
{
    /**
     * Finds a restricted assignment, retaining lexical shadowing through nested statements.
     */
    public function forbidden(Node $body, ?M\Routine\ParameterList $parameters = null): bool
    {
        $weights = new GeneralWeights();
        $key = $weights->key('autocommit');
        $local = $parameters === null || array_filter($parameters->parameters, static fn (M\Routine\Parameter $parameter): bool => $parameter->key() === $key) !== [];
        return $this->within($body, $local);
    }

    /**
     * Visits a statement with the lexical binding inherited from its parent.
     */
    public function within(Node $statement, bool $local): bool
    {
        if ($statement instanceof M\Routine\Program\Block) {
            $weights = new GeneralWeights();
            $key = $weights->key('autocommit');
            foreach ($statement->declarations as $declaration) {
                if ($declaration instanceof M\Routine\Program\VariableDeclaration) {
                    $local = $local || array_filter($declaration->names, static fn (Name $name): bool => $weights->key($name->value) === $key) !== [];
                }
            }
        }
        if ($statement instanceof M\Account\SetPassword) {
            return true;
        }
        if ($statement instanceof M\Utility\Set\SetVariables || $statement instanceof M\Routine\Program\DeferredAssignments) {
            foreach ($statement->items as $item) {
                if ($this->restricted($item, $local)) {
                    return true;
                }
            }
        }
        foreach ((new ReturnSearch())->nested($statement) as $nested) {
            if ($this->within($nested, $local)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Distinguishes system targets, shadowed names and session user variables.
     */
    public function restricted(M\Utility\Set\SetItem|M\Routine\Program\DeferredAssignment $item, bool $local): bool
    {
        if ($item instanceof M\Utility\Set\PasswordAssignment) {
            return true;
        }
        $target = match (true) {
            $item instanceof M\Utility\Set\SystemAssignment => $item->variable,
            $item instanceof M\Utility\Set\NameAssignment => $item->qualifier === null ? $item->name : null,
            $item instanceof M\Routine\Program\DeferredAssignment => $item->qualifier === null ? $item->target : null,
            default => null,
        };
        if ($target instanceof M\Variable\SystemTarget) {
            return $target->instance === null && strcasecmp($target->name->value, 'autocommit') === 0;
        }
        return $target instanceof Name && !$local && strcasecmp($target->value, 'autocommit') === 0;
    }
}
