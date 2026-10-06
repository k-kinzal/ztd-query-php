<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;

/**
 * Several statements written as one input, each a request of its own.
 *
 * Every statement is derived against the same context. An earlier statement is
 * not executed for a later one: a CREATE in the script declares nothing for
 * the statements after it. The declarations and diagnostics of every member
 * are those of the script; the rows of a member are not, since a script has
 * no single row set.
 *
 * @visibility public
 * @example Keeping the statements of one input apart
 *     $script = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1; SELECT 2');
 *     [count($script->statement->statements), $script->toString()] // => [2, 'SELECT 1; SELECT 2']
 */
final class Script implements Statement
{
    use Snapshot;

    /**
     * @var list<Statement> The statements in input order
     */
    public readonly array $statements;

    /**
     * @param list<Statement> $statements The statements in input order; not exactly one
     */
    public function __construct(array $statements)
    {
        $this->statements = Check::listOf($statements, Statement::class, 'A script holds statements.');
        Check::input(count($this->statements) !== 1, 'A script holds no statement or several; one statement is its own root.');
        foreach ($this->statements as $statement) {
            Check::input(!$statement instanceof self, 'A script does not nest.');
        }
    }

    /**
     * Derives each statement against the same unchanged context; the script itself returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        foreach ($this->statements as $statement) {
            $derivation->member($statement);
        }
    }

    /**
     * Writes the statements separated by terminators.
     */
    public function render(Output $out): void
    {
        foreach ($this->statements as $position => $statement) {
            if ($position > 0) {
                $out->symbol(';');
            }
            $out->node($statement);
        }
    }
}
