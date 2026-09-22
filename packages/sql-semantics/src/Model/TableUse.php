<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\TableDefinition;

/**
 * One occurrence of a declared table in a query scope.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
 *     $statement->relations[1]->alias // => 'b'
 *
 * @visibility public
 */
abstract class TableUse
{
    /**
     * @param string $id Query-local relation identity
     * @param string $scopeId Owning scope
     * @param TableDefinition $declaration Resolved table
     * @param string|null $alias Explicit alias, hiding the declaration name in this scope
     * @param Node $source Original table reference
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $id,
        public readonly string $scopeId,
        public readonly TableDefinition $declaration,
        public readonly ?string $alias,
        public readonly Node $source,
    ) {
        if ($id === '' || $scopeId === '') {
            throw new InvalidStructure('A relation requires occurrence and scope identities.');
        }
    }
    /**
     * @return list<Expression>
     */
    abstract public function resultExpressions(): array;

    /**

     * @visibility SqlSemantics

     */
    abstract public function withScope(string $scopeId): static;
}
