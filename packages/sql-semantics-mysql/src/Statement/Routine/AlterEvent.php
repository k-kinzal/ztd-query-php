<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Routine\EventClauses;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Rules\Routine\StatementSequence;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Schedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * ALTER EVENT: changes the schedule, name, status, comment or statement of an event.
 *
 * Every clause is optional in the grammar; a statement without any clause
 * is still structured (the server rejects it). The statement changes no
 * context. The facts follow MYSQL-EVENT-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-event.html.
 *
 * @visibility public
 * @example Reading the changes of an event
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER EVENT e RENAME TO shop.f ENABLE');
 *     [$alter->statement->newName->name->value, $alter->statement->status, $alter->statement->schedule] // => ['f', \SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus::Enable, null]
 */
final class AlterEvent implements Statement
{
    use Snapshot;

    /**
     * @var ProgramStatement|Statement|null The new statement of the event, when written
     */
    public readonly ProgramStatement|Statement|null $body;

    /**
     * @param QualifiedName $name The event name with its optional database
     * @param Schedule|null $schedule The new schedule, when written
     * @param Completion|null $completion The ON COMPLETION clause, when written
     * @param QualifiedName|null $newName The name of RENAME TO, when written
     * @param EventStatus|null $status The status clause, when written
     * @param Text|null $comment The COMMENT clause, when written
     * @param Node|null $body The new statement after DO, when written: a program statement or an SQL statement
     * @param Account|null $definer The DEFINER clause, when written
     * @throws InvalidConstruction When the body is of another class
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly ?Schedule $schedule = null,
        public readonly ?Completion $completion = null,
        public readonly ?QualifiedName $newName = null,
        public readonly ?EventStatus $status = null,
        public readonly ?Text $comment = null,
        ?Node $body = null,
        public readonly ?Account $definer = null,
    ) {
        Check::input($name->catalog === null && $newName?->catalog === null, 'An event name has at most a database qualifier.');
        Check::input($comment === null || $comment->radix === null, 'A comment is written as a quoted string.');
        $this->body = $body === null ? null : (new StatementSequence())->member($body);
    }

    /**
     * Derives the schedule and the body, when written.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ProgramFacts())->event($this->schedule, $this->body, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER');
        if ($this->definer !== null) {
            $out->keyword('DEFINER')->symbol('=')->node($this->definer);
        }
        $out->keyword('EVENT');
        (new ProgramNames())->qualified($out, $this->name);
        if ($this->schedule !== null) {
            $out->keyword('ON', 'SCHEDULE')->node($this->schedule);
        }
        (new EventClauses())->write($out, $this->completion, null, null);
        if ($this->newName !== null) {
            $out->keyword('RENAME', 'TO');
            (new ProgramNames())->qualified($out, $this->newName);
        }
        (new EventClauses())->write($out, null, $this->status, $this->comment);
        if ($this->body !== null) {
            $out->keyword('DO')->node($this->body);
        }
    }
}
