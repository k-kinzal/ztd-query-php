<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill;
use SqlSemantics\Statement\Scalar;

/**
 * Distinguishes standalone process identifiers from stored invocation-time uses.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProcessOperand
{
    /**
     * @param Lowering $lowering The selected grammar's lowering context
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Retains every complete standalone process expression and its preparation conditions.
     */
    public function statement(Form $form): Kill
    {
        $statement = $this->read($form);
        return $statement instanceof DeferredKill ? new Kill($statement->scope, $statement->process) : $statement;
    }

    /**
     * Reads a stored KILL while leaving other statement families to their dispatcher.
     */
    public function program(Node $node): Kill|DeferredKill|null
    {
        while ($node->name !== 'kill') {
            $children = array_values(array_filter($node->children, static fn ($child): bool => $child instanceof Node));
            if (count($children) !== 1) {
                return null;
            }
            $node = $children[0];
        }
        return $this->read($this->lowering->form($node));
    }

    /**
     * Preserves the operand's semantic role without substituting a scalar.
     */
    public function read(Form $form): Kill|DeferredKill
    {
        $scope = (new Instance\InstanceRule($this->lowering))->scope($form->node(1));
        $process = $this->lowering->expressions->operand($form->node(2));
        return $process instanceof Scalar ? new Kill($scope, $process) : new DeferredKill($scope, $process);
    }
}
