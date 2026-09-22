<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\BoundStatement;

/**
 * Preserves utility operations and binds their embedded query dependencies.
 *
 * @visibility SqlSemantics
 */
final class UtilityBinder
{
    /**
     * Resolves embedded queries in the caller's semantic environment.
     */
    public function __construct(public readonly QueryContext $context)
    {
    }

    /**
     * Binds DDL, transaction, maintenance, and administrative grammar statements.
     */
    public function bind(Node $source, Node $statement, string $kind): BoundStatement
    {
        $id = $this->context->ids->scope();
        $children = Tree::significant($statement);
        while (count($children) === 1 && $children[0] instanceof Node) {
            $statement = $children[0];
            $children = Tree::significant($statement);
        }
        $commands = [];
        foreach ($children as $child) {
            if ($child instanceof Node) {
                array_push($commands, ...$this->commands($child));
            }
        }
        $statements = array_map(fn (Node $command): BoundStatement => (new StatementBinder($this->context->tables))->node($command, $command, $this->context), $commands);
        $queries = array_values(array_filter($statements, static fn (BoundStatement $command): bool => $command instanceof \SqlSemantics\Model\BoundQuery));
        $tables = $this->context->tables;
        $create = Tree::outer($statement, ['CreateStmt', 'create_table_stmt', 'create_table'])[0] ?? (preg_match('/^CREATE (TEMPORARY )?TABLE /i', Tree::text($statement)) === 1 ? $statement : null);
        $declarations = [];
        if ($create !== null) {
            $reader = new \SqlSemantics\Ast\SchemaReader($tables->identifiers, $tables->defaultSchema, $tables->diagnostics->report(...));
            $declarations[] = $reader->table($tables->identifiers->dialect === \SqlSemantics\Dialect::Sqlite ? $statement : $create);
        }
        $targets = array_map(fn (\SqlSemantics\Schema\TableDefinition $table): \SqlSemantics\Model\TableUse => new \SqlSemantics\Model\TableUse($this->context->ids->relation(), $id, $table, null, $table->source), $declarations);
        $index = (new \SqlSemantics\Ast\Definition\IndexReader($tables->identifiers, $tables->defaultSchema))->read($statement);
        if ($index !== null) {
            $target = $tables->resolve($index->table, $statement);
            $targets[] = new \SqlSemantics\Model\TableUse($this->context->ids->relation(), $id, $target, null, $statement);
        }
        $scope = new \SqlSemantics\Binding\Scope($tables->identifiers, $targets, queries: $this->context);
        $indexes = $index === null ? array_merge(...array_map(static fn ($table): array => $table->indexes, $declarations)) : [$index];
        $boundIndexes = array_map(static fn ($definition): \SqlSemantics\Model\Definition\IndexDeclaration => \SqlSemantics\Binding\Schema\IndexBinder::bind($definition, $scope), $indexes);
        $definitions = array_map(static fn ($target): \SqlSemantics\Model\Definition\TableDeclaration => (new \SqlSemantics\Binding\Schema\DefinitionBinder())->bind($target, $scope), array_slice($targets, 0, count($declarations)));
        $settings = in_array($kind, ['SET', 'RESET', 'PRAGMA'], true) ? (new \SqlSemantics\Binding\Configuration\SettingBinder())->bind($statement, $scope) : [];
        $expressions = [];
        foreach (Tree::outer($statement, ['a_expr', 'expr', 'SelectStmt', 'select_stmt', 'query_expression', 'select', ...array_column($commands, 'name')]) as $node) {
            if (in_array($node->name, ['a_expr', 'expr'], true)) {
                $expressions[] = (new \SqlSemantics\Binding\ExpressionBinder())->bind($node, $scope);
            }
        }
        $class = $declarations !== [] ? \SqlSemantics\Model\Statement\CreateTableStatement::class : ($index !== null ? \SqlSemantics\Model\Statement\CreateIndexStatement::class : (in_array($kind, ['SET', 'RESET', 'PRAGMA'], true) ? \SqlSemantics\Model\Statement\ConfigurationStatement::class : \SqlSemantics\Model\Statement\CommandStatement::class));
        return new $class($id, null, [], [], null, false, [], null, null, $source, clauses: ['arguments' => $expressions], kind: $kind, targets: $targets, queries: $queries, syntaxClauses: \SqlSemantics\Binding\Query\QueryNodes::clauses($statement), declarations: $declarations, settings: $settings, definitions: $definitions, statements: $statements, indexes: $boundIndexes);
    }
    /**
     * Stops at each nested command boundary, including utility wrappers around queries.
     *
     * @return list<Node>
     */
    public function commands(Node $node): array
    {
        if (str_ends_with($node->name, 'Stmt') || str_ends_with($node->name, '_stmt') || in_array($node->name, ['select', 'query_expression', 'statement'], true)) {
            return [$node];
        }
        $result = [];
        foreach ($node->children as $child) {
            if ($child instanceof Node && Tree::hasTokens($child)) {
                array_push($result, ...$this->commands($child));
            }
        }
        return $result;
    }
}
