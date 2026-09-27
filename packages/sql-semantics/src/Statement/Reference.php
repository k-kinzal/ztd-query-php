<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Statement\Declaration\TableDefinition;

/**
 * A table name written in a statement and what it resolves to.
 *
 * The value is the name as written, so it can be found and rewritten in the
 * statement. The name parts are decoded, in writing order, the schema before
 * the table when the name is qualified.
 *
 * @visibility public
 * @example Reading a resolved reference
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $users = $semantics->analyze('CREATE TABLE users (id INTEGER)', []);
 *     $reference = $semantics->analyze('SELECT id FROM users', [$users])->resolution?->references[0];
 *     [$reference?->name, $reference?->kind?->name, $reference?->declaration === $users] // => [['users'], 'Dependency', true]
 */
final class Reference
{
    /**
     * @param Element $value The name as written in the statement
     * @param non-empty-list<string> $name The decoded name parts, in writing order
     * @param ReferenceKind $kind What the name resolves to
     * @param Statement|null $declaration The dependency that declares the table, when one does
     * @param TableDefinition|null $table The declared table, when the name resolves to one
     * @param bool $conditional Whether the statement only declares or drops the table if that is possible
     */
    public function __construct(
        public readonly Element $value,
        public readonly array $name,
        public readonly ReferenceKind $kind,
        public readonly ?Statement $declaration = null,
        public readonly ?TableDefinition $table = null,
        public readonly bool $conditional = false,
    ) {
        Declaration\Invariant::names($name);
    }
}
