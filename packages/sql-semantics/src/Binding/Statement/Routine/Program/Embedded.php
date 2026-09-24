<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\AssignmentStatement;
use SqlSemantics\Model\Definition\Routine\Body\EmbeddedStatement;
use SqlSemantics\Model\Definition\Routine\Body\SelectIntoStatement;

/**
 * Binds an ordinary statement of a body with the same semantic forms as direct SQL, in which program names resolve.
 * @visibility SqlSemantics
 */
final class Embedded
{
    /**
     * Local SET and SELECT INTO targets become program statements; every other statement is checked for stored program use.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Node $node, ProgramFrame $frame): EmbeddedStatement|AssignmentStatement|SelectIntoStatement
    {
        $statement = Tree::child($node, ['simple_statement', 'statement']) ?? $node;
        Placement::nested($statement);
        $program = StatementBinder::operation($statement) === 'SET' ? Assignments::bind($statement, $frame) : ProgramRetrievals::bind($statement, $frame);
        if ($program !== null) {
            return $program;
        }
        $bound = (new StatementBinder($frame->context->tables))->node($statement, $statement, $frame->context);
        Placement::check($bound, $statement, $frame);
        return new EmbeddedStatement($bound);
    }
}
