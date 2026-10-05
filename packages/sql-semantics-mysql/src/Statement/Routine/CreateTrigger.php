<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Rules\Routine\StatementSequence;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerOrder;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * CREATE TRIGGER: a statement that runs for each row an INSERT, UPDATE or DELETE changes in a table.
 *
 * The statement is structured as a request: it declares nothing to a
 * context. The facts follow MYSQL-TRIGGER-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 *
 * @visibility public
 * @example Reading a trigger definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET @changed = 1');
 *     [$create->statement->time->value, $create->statement->event->value, $create->statement->table->name->name->value] // => ['BEFORE', 'UPDATE', 't']
 */
final class CreateTrigger implements Statement
{
    use Snapshot;

    /**
     * @var ProgramStatement|Statement The statement the trigger runs
     */
    public readonly ProgramStatement|Statement $body;

    /**
     * @param QualifiedName $name The trigger name with its optional database
     * @param TriggerTime $time Whether the trigger runs before or after the row change
     * @param TriggerEvent $event The kind of row change that activates the trigger
     * @param TriggerTable $table The table the trigger belongs to
     * @param Node $body The statement the trigger runs: a program statement or an SQL statement
     * @param TriggerOrder|null $order The FOLLOWS or PRECEDES clause (MySQL 5.7 and later)
     * @param Account|null $definer The DEFINER clause, when written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written (MySQL 8.0.29 and later)
     * @throws InvalidConstruction When the body is of another class
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly TriggerTime $time,
        public readonly TriggerEvent $event,
        public readonly TriggerTable $table,
        Node $body,
        public readonly ?TriggerOrder $order = null,
        public readonly ?Account $definer = null,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null, 'A trigger name has at most a database qualifier.');
        $this->body = (new StatementSequence())->member($body);
    }

    /**
     * Derives the table and the body, in which NEW and OLD denote the row of the table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ProgramFacts())->trigger($this->table, $this->time, $this->event, $this->body, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->definer !== null) {
            $out->keyword('DEFINER')->symbol('=')->node($this->definer);
        }
        $out->keyword('TRIGGER');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new ProgramNames())->qualified($out, $this->name);
        $out->keyword($this->time->value, $this->event->value, 'ON')->node($this->table)->keyword('FOR', 'EACH', 'ROW')->node($this->order)->node($this->body);
    }
}
