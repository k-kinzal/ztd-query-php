<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * An event schedule outside the scalar operand domain, with its preceding header.
 *
 * The diagnostic preserves database and definer precedence without publishing
 * an invalid event statement or retaining source SQL and parser nodes.
 *
 * @visibility public
 * @example Keeping the event identity on a refused scalar operand
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('e')), new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, 2), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::At, true)))->message() // => 'Operand should contain 1 column(s), not 2.'
 */
final class RefusedOperand implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $name The event name and optional database
     * @param OperandWidth|NotSupportedYet $problem The schedule's preceding dependency or scalar-width condition
     * @param Account|null $definer The account preceding the event name
     */
    public function __construct(public readonly QualifiedName $name, public readonly OperandWidth|NotSupportedYet $problem, public readonly ?Account $definer = null)
    {
        Check::input($name->catalog === null, 'An event name has at most a database qualifier.');
    }

    /**
     * Describes the retained schedule condition.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
