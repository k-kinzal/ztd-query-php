<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A CREATE TABLE header preceding a refused partitioning or query operand.
 *
 * Engine selection belongs to the retained header and precedes contextual
 * interpretation of the tail. Grammar conditions still precede both phases.
 * No invalid ordinary table statement or parser subtree is published.
 *
 * @visibility public
 * @example Retaining an intrinsic tail condition
 *     $problem = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedCreateTail(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [], [], 0, false, new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, 2));
 *     $problem->message() // => 'Operand should contain 1 column(s), not 2.'
 */
final class RefusedCreateTail implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<TableElement> The completed column and key declarations
     */
    public readonly array $elements;

    /**
     * @var list<TableOption> The completed table options, in source order
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The table being declared
     * @param list<TableElement> $elements The completed column and key declarations
     * @param list<TableOption> $options The completed table options
     * @param int $temporaryWords The number of TEMPORARY words in the header
     * @param bool $ifNotExists Whether IF NOT EXISTS is present
     * @param Diagnostic $problem The retained tail condition and its dependencies
     */
    public function __construct(public readonly QualifiedName $name, array $elements, array $options, public readonly int $temporaryWords, public readonly bool $ifNotExists, public readonly Diagnostic $problem)
    {
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($name);
        Check::input($name->catalog === null, 'A table name has at most a database qualifier.');
        Check::input($temporaryWords >= 0, 'TEMPORARY is written a non-negative number of times.');
        $this->elements = Check::listOf($elements, TableElement::class, 'Table elements are an ordered list of table elements.');
        $this->options = Check::listOf($options, TableOption::class, 'Table options are an ordered list of table options.');
    }

    /**
     * Describes the tail condition after preceding header checks.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
