<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * A stored SET list containing at least one deferred assignment operand.
 *
 * All items and their scopes remain in written order. Ordinary SET statements
 * retain their narrower SetVariables type. This instruction is never published
 * as a standalone statement or certified for execution by Semantics.
 *
 * @visibility public
 * @example Refusing an ordinary SET list in this role
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignments([new \SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('x')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredAssignments implements ProgramStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<SetItem|DeferredAssignment> The complete ordered SET list
     */
    public readonly array $items;

    /**
     * @param list<SetItem|DeferredAssignment> $items Ordinary and deferred assignments
     */
    public function __construct(array $items, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $checked = [];
        $deferred = false;
        foreach (Check::listOf($items, Node::class, 'Stored SET has at least one item.', 1) as $item) {
            Check::input($item instanceof SetItem || $item instanceof DeferredAssignment, 'Stored SET holds assignment items.');
            $checked[] = $item;
            $deferred = $deferred || $item instanceof DeferredAssignment;
        }
        Check::input($deferred, 'A deferred SET contains an invocation-time operand constraint.');
        $this->items = $checked;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the retained targets and values in the enclosing program scope.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        foreach ($this->items as $item) {
            if ($item instanceof DeferredAssignment) {
                $item->deriveProgram($derivation, $scope);
            } else {
                $derivation->program(new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables([$item]), $scope->environment);
            }
        }
    }

    /**
     * Writes the complete ordered SET list.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET')->list($this->items);
    }
}
