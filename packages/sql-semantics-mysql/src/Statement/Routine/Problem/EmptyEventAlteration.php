<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * An ALTER EVENT header without a subsequent change clause.
 *
 * The optional definer does not supply a change clause. Typed header operands
 * preserve name and database precedence without retaining source SQL or ASTs.
 * Observed on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example Describing the missing change without retaining source text
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('e'))))->message() // => 'ALTER EVENT changes at least one event property after its name.'
 */
final class EmptyEventAlteration implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $name The event name with its optional database
     * @param Account|null $definer The preceding account operand
     * @param int $offset The end of the completed header, used only to format its input refusal
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?Account $definer = null, public readonly int $offset = 0)
    {
        \SqlSemantics\Diagnostic\Check::input($offset >= 0, 'The event input position is nonnegative.');
        \SqlSemantics\Diagnostic\Check::input($name->catalog === null, 'An event name has at most a database qualifier.');
    }

    /**
     * Describes the missing event change.
     */
    public function message(): string
    {
        return 'ALTER EVENT changes at least one event property after its name.';
    }
}
