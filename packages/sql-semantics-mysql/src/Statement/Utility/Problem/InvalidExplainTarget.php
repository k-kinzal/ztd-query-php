<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An EXPLAIN INTO target whose empty or overlong name cannot receive the plan.
 *
 * Reading an absent user variable has a different domain from assigning it.
 * Every release supporting EXPLAIN INTO limits its target to 64 characters.
 * The decoded target survives without its SQL or parser node.
 *
 * @visibility public
 * @example Retaining an empty assignment target
 *     $target = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name(''));
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget($target))->message() // => "User variable name '' is illegal"
 */
final class InvalidExplainTarget implements Diagnostic
{
    use Snapshot;

    /**
     * @param UserVariable $target The complete decoded target
     */
    public function __construct(public readonly UserVariable $target)
    {
        Check::input(!(new ExplainFacts())->target($target), 'A refused EXPLAIN target has an empty or overlong name.');
    }

    /**
     * Describes the target refusal; protocol formatting owns message byte limits.
     */
    public function message(): string
    {
        return "User variable name '" . $this->target->name->value . "' is illegal";
    }
}
