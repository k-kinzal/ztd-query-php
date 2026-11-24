<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The resolved coordinate or checked absence of one DROP SEQUENCE selector.
 *
 * @visibility public
 * @example An absolute sequence selector
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceDropTarget(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('ticket'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('public')))->quoted() // => '"public"."ticket"'
 */
final class SequenceDropTarget
{
    use Snapshot;

    /**
     * Checks which qualified coordinate is required by the resolved role.
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly ?Identifier $schema,
        public readonly SequenceDropPresence $presence = SequenceDropPresence::Existing,
    ) {
        Check::input($schema !== null || $presence === SequenceDropPresence::MissingSequence, 'A sequence selector requires an absolute namespace.');
        Check::input(
            $schema === null || ($schema->value !== 'pg_toast' && !IndexSchemas::sessionInternal($schema->value)),
            'A sequence selector cannot use a physical TOAST or temporary schema.'
        );
        if ($presence === SequenceDropPresence::MissingNamespace) {
            Check::input(
                $schema->value !== 'pg_temp' && !IndexSchemas::stock($schema->value),
                'A missing sequence namespace must be an application namespace.'
            );
        }
    }

    /**
     * Emits the checked qualification, or the unqualified absence selector.
     */
    public function quoted(): string
    {
        return ($this->schema === null ? '' : $this->schema->quoted() . '.') . $this->name->quoted();
    }
}
