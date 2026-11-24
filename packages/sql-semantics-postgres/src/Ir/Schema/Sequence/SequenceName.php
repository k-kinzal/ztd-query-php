<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The absolute local identity of a sequence.
 *
 * @visibility public
 * @example Naming a sequence
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceName(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('app'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('ticket')))->quoted() // => '"app"."ticket"'
 */
final class SequenceName
{
    use Snapshot;

    /**
     * Uses a persistent schema or the current session's temporary scope.
     */
    public function __construct(public readonly Identifier $schema, public readonly Identifier $name)
    {
        Check::input(!IndexSchemas::sessionInternal($schema->value), 'A sequence name cannot select a numbered temporary schema.');
    }

    /**
     * Delimits both coordinates independently.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->name->quoted();
    }
}
