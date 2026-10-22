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
     * @var list<\SqlSemantics\Statement\Operand> Completed schedule expressions needed for earlier name resolution
     */
    public readonly array $operands;

    /**
     * @param QualifiedName $name The event name and optional database
     * @param OperandWidth|NotSupportedYet|\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse $problem The schedule's preceding dependency or scalar-width condition
     * @param Account|null $definer The account preceding the event name
     * @param list<\SqlSemantics\Statement\Operand> $operands The complete timestamp, interval and bounds
     */
    public function __construct(public readonly QualifiedName $name, public readonly OperandWidth|NotSupportedYet|\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse $problem, public readonly ?Account $definer = null, array $operands = [])
    {
        $this->operands = Check::listOf($operands, \SqlSemantics\Statement\Operand::class, 'Schedule dependencies are complete semantic operands.');
        Check::input(!$problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse || $problem->rule === \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::RecursiveReferencePosition, 'Only lexical recursive-reference constraints precede schedule dependency restrictions.');
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
