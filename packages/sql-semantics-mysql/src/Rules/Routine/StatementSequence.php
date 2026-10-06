<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Statement;

/**
 * Checks and writes the statement lists of stored programs.
 *
 * Rule: MYSQL-PROGRAM-SEQUENCE-001. A member of a statement list is a
 * statement of the stored program language or any SQL statement; every
 * member is written followed by a semicolon, as the grammar requires
 * inside compound statements. Terminates: one pass over the list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/begin-end.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StatementSequence
{
    /**
     * Narrows a constructor argument to a list of program statements and SQL statements.
     *
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $statements
     * @return ($minimum is positive-int ? non-empty-list<ProgramStatement|Statement> : list<ProgramStatement|Statement>)
     * @throws InvalidConstruction When a member is no statement, or the list is shorter than the minimum
     */
    public function members(array $statements, int $minimum = 0): array
    {
        return array_map($this->member(...), Check::listOf($statements, Node::class, 'A statement list holds statements.', $minimum));
    }

    /**
     * Narrows a node to a program statement or an SQL statement.
     *
     * @throws InvalidConstruction When the node is neither
     */
    public function member(Node $statement): ProgramStatement|Statement
    {
        if ($statement instanceof ProgramStatement || $statement instanceof Statement) {
            return $statement;
        }

        throw new InvalidConstruction('A member of a stored program is a program statement or an SQL statement.');
    }

    /**
     * Writes the statements, each followed by a semicolon.
     *
     * @param list<Node> $statements
     */
    public function write(Output $out, array $statements): void
    {
        foreach ($statements as $statement) {
            $out->node($statement)->symbol(';');
        }
    }
}
