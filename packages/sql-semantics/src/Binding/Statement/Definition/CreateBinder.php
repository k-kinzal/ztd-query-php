<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds table and index declarations together with their declared-column scopes.
 * @visibility SqlSemantics
 */
final class CreateBinder
{
    /**
     * Returns a declaration when this operation defines a table or an index.
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        $tables = $context->tables;
        $create = Tree::outer($statement, ['CreateStmt', 'create_table_stmt', 'create_table'])[0] ?? (preg_match('/^CREATE (TEMPORARY )?TABLE /i', Tree::text($statement)) === 1 ? $statement : null);
        $declarations = [];
        if ($create !== null) {
            $copy = TableLikeBinder::bind($origin, $create, $context);
            if ($copy !== null) {
                return $copy;
            }
            $reader = new \SqlSemantics\Ast\SchemaReader($tables->identifiers, $tables->defaultSchema, $tables->diagnostics->report(...));
            $declarations[] = \SqlSemantics\Binding\Schema\DeclarationBinder::bind($reader->table($tables->identifiers->dialect === \SqlSemantics\Dialect::Sqlite ? $statement : $create), $context);
        }
        $targets = array_map(fn (\SqlSemantics\Schema\TableDefinition $table): \SqlSemantics\Model\Relation\TableReference => new \SqlSemantics\Model\Relation\TableReference($context->ids->relation(), $origin->scopeId, $table, new \SqlSemantics\Model\Relation\QualifiedName($table->schema === '' ? [$table->name] : [$table->schema, $table->name]), null, $table->source), $declarations);
        $index = (new \SqlSemantics\Ast\Definition\IndexReader($tables->identifiers, $tables->defaultSchema))->read($statement);
        if ($index !== null) {
            $target = $tables->resolve($index->table, $statement);
            $targets[] = \SqlSemantics\Binding\Query\TableOccurrence::bind($context->ids->relation(), $origin->scopeId, $target, $tables->name($index->table, $target), null, $statement);
        }
        $scope = new \SqlSemantics\Binding\Scope($tables->identifiers, $targets, queries: $context);
        $indexes = $index === null ? array_merge(...array_map(static fn ($table): array => $table->indexes, $declarations)) : [$index];
        $boundIndexes = array_map(static fn ($definition): \SqlSemantics\Model\Definition\IndexDeclaration => \SqlSemantics\Binding\Schema\IndexBinder::bind($definition, $scope), $indexes);
        $definitions = array_map(static fn ($target): \SqlSemantics\Model\Definition\TableDeclaration => (new \SqlSemantics\Binding\Schema\DefinitionBinder())->bind($target, $scope), array_slice($targets, 0, count($declarations)));
        if ($definitions !== []) {
            return new \SqlSemantics\Model\Statement\CreateTableStatement($origin, $definitions[0], $boundIndexes, str_contains(strtoupper(Tree::text($statement)), 'IF NOT EXISTS'));
        }
        if ($index !== null) {
            return new \SqlSemantics\Model\Statement\CreateIndexStatement($origin, $boundIndexes[0], $targets[0], ($index->options['if_not_exists'] ?? false) === true, ($index->options['concurrently'] ?? false) === true);
        }
        return null;
    }
}
