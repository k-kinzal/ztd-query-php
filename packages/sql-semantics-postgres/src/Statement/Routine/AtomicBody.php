<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\BodyFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `BEGIN ATOMIC statement; ... END`: an SQL-standard routine body of several statements.
 *
 * The statements are parsed and checked when the routine is created, and
 * they see the parameters. Empty statements between the semicolons request
 * nothing and are not kept, as the server discards them.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Counting the statements of an empty block
 *     count((new \SqlSemantics\Platform\PostgreSql\Statement\Routine\AtomicBody([]))->statements) // => 0
 */
final class AtomicBody implements Clause
{
    use Snapshot;

    /**
     * @var list<Statement|ReturnStatement> The statements in order
     */
    public readonly array $statements;

    /**
     * @param list<Node> $statements The statements in order: statements and RETURN statements
     *
     * @throws InvalidConstruction When an item is neither a statement nor a RETURN statement
     */
    public function __construct(array $statements)
    {
        $checked = [];
        foreach (Check::listOf($statements, Node::class, 'The statements of a block are a list of nodes.') as $statement) {
            if (!$statement instanceof Statement && !$statement instanceof ReturnStatement) {
                throw new InvalidConstruction('A block holds statements and RETURN statements.');
            }
            $checked[] = $statement;
        }
        $this->statements = $checked;
    }

    /**
     * Derives every statement with the parameters visible.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new BodyFacts())->derive($this->statements, $derivation, $environment);
    }

    /**
     * Writes BEGIN ATOMIC, each statement followed by a semicolon, and END.
     */
    public function render(Output $out): void
    {
        $out->keyword('BEGIN', 'ATOMIC');
        foreach ($this->statements as $statement) {
            $out->node($statement)->symbol(';');
        }
        $out->keyword('END');
    }
}
