<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The sequence members of named schemas at statement execution.
 * @visibility public
 * @example Selecting the sequences in a decoded schema
 *     $schema = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('Track.Events');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequencesInSchemas([$schema]))->toString() // => 'ALL SEQUENCES IN SCHEMA "Track.Events"'
 */
final class SequencesInSchemas
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier>
     */
    public readonly array $schemas;

    /**
     * Detaches the nonempty schema list and requires portable temporary scope.
     * @param list<Identifier> $schemas
     */
    public function __construct(array $schemas)
    {
        $list = Check::listOf($schemas, Identifier::class, 'Schema-wide sequence privileges require named schemas.', 1);
        foreach ($list as $schema) {
            Check::input(!IndexSchemas::sessionInternal($schema->value), 'Sequence privileges cannot use a numbered temporary schema.');
        }
        $this->schemas = $list;
    }

    /**
     * Emits the collection operation and independently quotes each schema.
     */
    public function toString(): string
    {
        $names = [];
        foreach ($this->schemas as $schema) {
            $names[] = $schema->quoted();
        }

        return 'ALL SEQUENCES IN SCHEMA ' . implode(', ', $names);
    }
}
