<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Refinements;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A window relationship that is contradictory independently of catalog state.
 *
 * The specification and its ordered ancestor definitions retain the complete
 * relationship. A dependent partition needs no ancestor to establish its
 * contradiction. Names belong to the query block, never to source SQL.
 *
 * @visibility public
 * @example Refusing a relationship without a contradiction
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement(new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class WindowRefinement implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<WindowDefinition> The direct parent followed by successive ancestors
     */
    public readonly array $ancestors;

    /**
     * @param WindowSpec|DeferredWindowSpec $window The dependent specification
     * @param list<WindowDefinition> $ancestors The connected ancestry, starting at its base
     * @param Name|null $name Its name in WINDOW, or null for an inline OVER specification
     */
    public function __construct(public readonly WindowSpec|DeferredWindowSpec $window, array $ancestors = [], public readonly ?Name $name = null)
    {
        $this->ancestors = Check::listOf($ancestors, WindowDefinition::class, 'Window ancestors are ordered complete definitions.');
        $rules = new Refinements();
        Check::input($rules->connected($window, $this->ancestors), 'Window ancestors follow the specification\'s base names without a cycle.');
        Check::input($rules->condition($window, $this->ancestors) !== null, 'A refinement diagnostic retains an intrinsic window relationship.');
    }

    /**
     * Describes the relationship established by the retained operands.
     */
    public function message(): string
    {
        $condition = (new Refinements())->condition($this->window, $this->ancestors);
        Check::invariant($condition !== null, 'A refinement diagnostic has a fixed relationship.');
        return $condition->value;
    }
}
