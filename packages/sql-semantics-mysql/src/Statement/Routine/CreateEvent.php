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
 * CREATE EVENT: a statement the event scheduler runs once or repeatedly.
 *
 * The statement is structured as a request: it declares nothing to a
 * context. The facts follow MYSQL-EVENT-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 *
 * @visibility public
 * @example Reading an event definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE EVENT e ON SCHEDULE EVERY 1 DAY ON COMPLETION PRESERVE DISABLE COMMENT 'nightly' DO DELETE FROM log");
 *     [$create->statement->completion, $create->statement->status, $create->statement->comment->value] // => [\SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion::Preserve, \SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus::Disable, 'nightly']
 */
final class CreateEvent implements Statement
{
    use Snapshot;

    /**
     * @var ProgramStatement|Statement The statement the event runs
     */
    public readonly ProgramStatement|Statement $body;

    /**
     * @param QualifiedName $name The event name with its optional database
     * @param Schedule $schedule When the event runs
     * @param Node $body The statement the event runs: a program statement or an SQL statement
     * @param Completion|null $completion The ON COMPLETION clause, when written
     * @param EventStatus|null $status The status clause, when written
     * @param Text|null $comment The COMMENT clause, when written
     * @param Account|null $definer The DEFINER clause, when written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @throws InvalidConstruction When the body is of another class
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly Schedule $schedule,
        Node $body,
        public readonly ?Completion $completion = null,
        public readonly ?EventStatus $status = null,
        public readonly ?Text $comment = null,
        public readonly ?Account $definer = null,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null, 'An event name has at most a database qualifier.');
        Check::input($comment === null || $comment->radix === null, 'A comment is written as a quoted string.');
        $this->body = (new StatementSequence())->member($body);
    }

    /**
     * Derives the schedule and the body.
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
        $out->keyword('CREATE');
        if ($this->definer !== null) {
            $out->keyword('DEFINER')->symbol('=')->node($this->definer);
        }
        $out->keyword('EVENT');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new ProgramNames())->qualified($out, $this->name);
        $out->keyword('ON', 'SCHEDULE')->node($this->schedule);
        (new EventClauses())->write($out, $this->completion, $this->status, $this->comment);
        $out->keyword('DO')->node($this->body);
    }
}
