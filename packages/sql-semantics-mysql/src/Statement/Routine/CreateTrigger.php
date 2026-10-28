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
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

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
 *     [$create->time->value, $create->event->value, $create->table->name->name->value] // => ['BEFORE', 'UPDATE', 't']
 */
final class CreateTrigger extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

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
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        public readonly ?Definition\DefinitionSource $definitionSource = null,
    ) {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        ProgramNames::check($name);
        Check::input($name->catalog === null, 'A trigger name has at most a database qualifier.');
        $this->body = (new StatementSequence())->member($body);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Identifies distinct explicit databases while retaining the complete trigger body.
     */
    public function hasExecutionCondition(): bool
    {
        return !\SqlSemantics\Platform\MySql\Rules\Routine\Declaration\TriggerSchemas::compatible($this->name, $this->table);
    }

    /**
     * Derives the table and the body, in which NEW and OLD denote the row of the table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->derive($derivation, [$this->definer]);
        (new ProgramFacts())->trigger($this->table, $this->time, $this->event, $this->body, $derivation);
        (new \SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions())->publish($this, $derivation);
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
