<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Routine\BlockFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Rules\Routine\StatementSequence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A BEGIN ... END compound statement with its declarations, statements and optional label.
 *
 * The facts follow MYSQL-PROGRAM-BLOCK-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/begin-end.html.
 *
 * @visibility public
 * @example Reading a labeled block
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() work: BEGIN DECLARE a INT; SELECT a; END work');
 *     $block = $create->statement->body;
 *     [$block->label->value, count($block->declarations), count($block->statements)] // => ['work', 1, 1]
 */
final class Block implements ProgramStatement
{
    use Snapshot;

    /**
     * @var list<Declaration> The declarations in written order
     */
    public readonly array $declarations;

    /**
     * @var list<ProgramStatement|Statement> The statements in written order
     */
    public readonly array $statements;

    /**
     * @param list<Declaration> $declarations The declarations in written order
     * @param list<ProgramStatement|Statement> $statements The statements in written order
     * @param Name|null $label The label written before BEGIN
     * @param Name|null $endLabel The label written after END; only a labeled block has one
     * @throws InvalidConstruction When a member is of another class, or an end label is given without a label
     */
    public function __construct(array $declarations = [], array $statements = [], public readonly ?Name $label = null, public readonly ?Name $endLabel = null)
    {
        $this->declarations = Check::listOf($declarations, Declaration::class, 'A block declares variables, conditions, cursors and handlers.');
        $this->statements = (new StatementSequence())->members($statements);
        Check::input($endLabel === null || $label !== null, 'Only a labeled block has an end label.');
    }

    /**
     * Derives the declarations and the statements in the scope of the block.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new BlockFacts())->derive($this, $derivation, $scope);
    }

    /**
     * Writes the block.
     */
    public function render(Output $out): void
    {
        (new ProgramNames())->label($out, $this->label);
        $out->keyword('BEGIN');
        (new StatementSequence())->write($out, $this->declarations);
        (new StatementSequence())->write($out, $this->statements);
        $out->keyword('END');
        (new ProgramNames())->end($out, $this->endLabel);
    }
}
