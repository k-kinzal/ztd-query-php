<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\TableDefinition as ParsedTable;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Schema\DeclarationBinder;
use SqlSemantics\Binding\Schema\DefinitionBinder;
use SqlSemantics\Binding\Schema\IndexBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Definition\IndexDeclaration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Table\CreateTableFromQueryStatement;
use SqlSemantics\Model\Statement\Loading\DuplicateRows;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds a MySQL CREATE TABLE that declares its own columns and is filled from an input query.
 * @visibility SqlSemantics
 */
final class TableFromQuery
{
    /**
     * Binds the declared table in its own column scope and the input query in the statement scope.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Origin $origin, Node $source, ParsedTable $parsed, Node $query, QueryContext $context): CreateTableFromQueryStatement
    {
        $tables = $context->tables;
        $declaration = DeclarationBinder::bind($parsed, $context);
        $target = new TableReference($context->ids->relation(), $origin->scopeId, $declaration, new QualifiedName($declaration->schema === '' ? [$declaration->name] : [$declaration->schema, $declaration->name]), null, $declaration->source);
        $scope = new Scope($tables->identifiers, [$target], queries: $context);
        $indexes = array_map(static fn ($index): IndexDeclaration => IndexBinder::bind($index, $scope), $declaration->indexes);
        return new CreateTableFromQueryStatement($origin, (new DefinitionBinder())->bind($target, $scope), self::query($origin, $query, $context), $indexes, $parsed->ifNotExists, self::duplicates($source));
    }

    /**
     * Binds the input query of a table being created; MySQL refuses one that locks a stored table FOR UPDATE.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function query(Origin $origin, Node $query, QueryContext $context): \SqlSemantics\Model\BoundQuery
    {
        $bound = $context->bind($query);
        if ($origin->dialect === \SqlSemantics\Dialect::MySql && \SqlSemantics\Model\Query\Locking\TableCreationLocks::exclusive($bound)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::TableCreationLock, $query);
        }
        return $bound;
    }

    /**
     * Reads the IGNORE or REPLACE keyword that precedes the input query; MySQL 5 names the clause opt_duplicate.
     */
    public static function duplicates(Node $source): ?DuplicateRows
    {
        $clause = Tree::outer($source, ['duplicate', 'opt_duplicate'])[0] ?? null;
        $keyword = $clause === null ? null : ($clause->tokens()[0] ?? null);
        return $keyword === null ? null : DuplicateRows::tryFrom(strtoupper($keyword->text));
    }
}
