<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Connection;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A resolved SQLite attachment and the checked operand that selects it.
 *
 * @visibility public
 * @example Retaining the spelling that SQLite uses in a diagnostic
 *     $schema = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('archive');
 *     $spelling = new \SqlSemantics\Platform\Sqlite\Ir\Connection\AttachmentName(new \SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral('Archive'));
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Connection\DetachTarget($schema, $spelling))->name->name->value // => 'Archive'
 */
final class DetachTarget
{
    use Snapshot;

    /**
     * Checks that the requested name selects the recorded native attachment.
     * @throws InvalidConstruction When the requested name and attachment identity differ
     */
    public function __construct(public readonly Identifier $schema, public readonly AttachmentName $name)
    {
        Check::input($schema->key() === $name->name->key(), 'The DETACH name must match its resolved database.');
    }
}
