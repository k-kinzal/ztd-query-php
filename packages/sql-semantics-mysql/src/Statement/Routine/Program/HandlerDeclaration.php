<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Routine\StatementSequence;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * DECLARE ... HANDLER: the statement that runs when one of the listed conditions occurs in the block.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-handler.html.
 *
 * @visibility public
 * @example Reading a handler
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR NOT FOUND, 1051 BEGIN END; END');
 *     $handler = $create->statement->body->declarations[0];
 *     [$handler->action, count($handler->conditions)] // => [\SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction::Continue, 2]
 */
final class HandlerDeclaration implements Declaration
{
    use Snapshot;

    /**
     * @var non-empty-list<Condition> The conditions in written order
     */
    public readonly array $conditions;

    /**
     * @var ProgramStatement|Statement The statement the handler runs
     */
    public readonly ProgramStatement|Statement $statement;

    /**
     * @param HandlerAction $action What happens after the handler ran
     * @param list<Condition> $conditions The conditions handled; at least one
     * @param Node $statement The statement the handler runs: a program statement or an SQL statement
     * @throws InvalidConstruction When there is no condition or the statement is of another class
     */
    public function __construct(public readonly HandlerAction $action, array $conditions, Node $statement)
    {
        $this->conditions = Check::listOf($conditions, Condition::class, 'A handler names at least one condition.', 1);
        $this->statement = (new StatementSequence())->member($statement);
    }

    /**
     * Writes the declaration.
     */
    public function render(Output $out): void
    {
        $out->keyword('DECLARE', $this->action->value, 'HANDLER', 'FOR')->list($this->conditions)->node($this->statement);
    }
}
