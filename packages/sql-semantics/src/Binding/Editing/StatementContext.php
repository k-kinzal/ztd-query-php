<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Editing;

use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Analysis\Diagnostics;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Transformation\Context;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema;

/**
 * Recomputes semantic facts for a transformed structure using its original schema snapshot.
 *
 * @visibility SqlSemantics
 */
final class StatementContext implements Context
{
    /**
     * Retains an immutable snapshot, never a live database or mutable binder.
     */
    public function __construct(private readonly Schema $snapshot, private readonly ?\SqlSemantics\Binding\Query\QueryContext $query = null, private readonly ?\SqlSemantics\Binding\Scope $parent = null, private readonly bool $queryOnly = false)
    {
    }

    /**
     * Returns the interpretation context of this statement.
     */
    public function schema(): Schema
    {
        return $this->snapshot;
    }

    /**
     * @template T of BoundStatement
     * @param T $previous
     * @return T
     * @throws InvalidStructure
     */
    public function rebind(BoundStatement $previous, Tree $sql): BoundStatement
    {
        $statement = $this->bind($sql);
        $type = $previous::class;
        if (!$statement instanceof $type || $statement->kind !== $previous->kind) {
            throw new InvalidStructure('A transformation cannot change the statement operation.');
        }
        return $statement;
    }

    /**
     * Binds a constructed SQL structure and publishes it only after strict validation.
     */
    public function bind(Tree $sql): BoundStatement
    {
        $schema = $this->snapshot;
        $source = (new DialectParser($schema->dialect, $schema->grammarVersion))->parse($sql->toString());
        $tables = new TableResolver($schema, new Identifiers($schema->dialect), $schema->defaultSchema, new Diagnostics());
        if ($this->query !== null) {
            $context = new \SqlSemantics\Binding\Query\QueryContext($tables, clone $this->query->ids, $this->query->ctes, parameterTypes: $this->query->parameterTypes);
            return $this->queryOnly ? $context->bind($source, self::scope($this->parent, $tables)) : (new StatementBinder($tables))->bind($source, $context, self::scope($this->parent, $tables))->withContext($this);
        }
        return (new StatementBinder($tables))->bind($source)->withContext($this);
    }

    /**
     * Retains lexical names and identities while separating validation diagnostics.
     */
    public static function scope(?\SqlSemantics\Binding\Scope $scope, TableResolver $tables): ?\SqlSemantics\Binding\Scope
    {
        if ($scope === null) {
            return null;
        }
        $queries = new \SqlSemantics\Binding\Query\QueryContext($tables, ctes: $scope->queries->ctes ?? [], parameterTypes: $scope->queries->parameterTypes ?? []);
        return new \SqlSemantics\Binding\Scope($scope->identifiers, $scope->relations, $scope->extensions, self::scope($scope->parent, $tables), $queries, $scope->merged);
    }
}
