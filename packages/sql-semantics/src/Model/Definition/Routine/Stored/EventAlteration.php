<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The changes ALTER EVENT requests; omitted properties keep their current values.
 * @visibility public
 * @example Reading requested event changes
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER EVENT e RENAME TO archive.e DISABLE');
 *     $statement->changes->newName->parts // => ['archive', 'e']
 *     $statement->changes->schedule // => null
 */
final class EventAlteration
{
    /**
     * Requires at least one change, a nonempty new name and a MySQL comment literal.
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly OneTimeSchedule|RecurringSchedule|null $schedule = null,
        public readonly ?EventCompletion $completion = null,
        public readonly ?QualifiedName $newName = null,
        public readonly ?EventStatus $status = null,
        public readonly ?Literal $comment = null,
        public readonly ?ProgramStatement $body = null,
    ) {
        if ($schedule === null && $completion === null && $newName === null && $status === null && $comment === null && $body === null) {
            throw new InvalidStructure('ALTER EVENT changes at least one property.');
        }
        if ($newName !== null && (count($newName->parts) > 2 || in_array('', $newName->parts, true))) {
            throw new InvalidStructure('An event is renamed to one nonempty local or database-qualified name.');
        }
        if ($comment !== null && ($comment->type->dialect !== Dialect::MySql || $comment->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('An event comment requires a MySQL text literal.');
        }
        if ($body !== null) {
            ProgramStructure::check($body, ProgramKind::Event);
        }
    }
}
