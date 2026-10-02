<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Relation;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Table;

/**
 * One relation occurrence, distinct from the declaration shared by a self join.
 * @visibility public
 * @example Retaining a relation alias
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $table = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')), new \SqlSemantics\Statement\Identifier\Name('u'));
 *     $table->toString() // => 'users AS u'
 */
final class TableReference
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var list<Table>
     */
    public readonly array $declarations;

    /**
     * Derives every declaration match instead of accepting an unrelated table pointer.
     */
    public function __construct(public readonly Catalog $catalog, public readonly QualifiedName $name, public readonly ?Name $alias = null, public readonly bool $explicitAlias = true)
    {
        $this->declarations = $catalog->matchingTables($name);
    }

    /**
     * Returns the name visible in the query scope.
     */
    public function visibleName(): Name
    {
        return $this->alias ?? $this->name->name;
    }

    /**
     * Determines whether a column qualifier can designate this occurrence.
     */
    public function matches(?QualifiedName $qualifier): bool
    {
        if ($qualifier === null) {
            return true;
        }
        if (!$this->catalog->tableNames->equal($this->visibleName()->value, $qualifier->name->value)) {
            return false;
        }
        if ($qualifier->schema === null) {
            return true;
        }
        if ($this->alias !== null) {
            return false;
        }
        $resolvedName = $this->declarations[0]->name ?? $this->name;
        $schemas = $resolvedName->schema === null && $this->declarations === []
            ? $this->catalog->searchPath->schemas
            : [$resolvedName->schema ?? $this->catalog->declarationSchema];
        $catalog = $resolvedName->catalog ?? $this->catalog->currentCatalog;
        $schemaMatches = array_filter($schemas, fn (Name $schema): bool => $this->catalog->tableNames->equal($schema->value, $qualifier->schema->value));
        return $schemaMatches !== []
            && ($qualifier->catalog === null || ($catalog !== null && $this->catalog->tableNames->equal($catalog->value, $qualifier->catalog->value)));
    }

    /**
     * Reconstructs the named relation and its correlation name.
     */
    public function toString(): string
    {
        return $this->name->toString() . ($this->alias === null ? '' : ($this->explicitAlias ? ' AS ' : ' ') . $this->alias->toString());
    }
}
