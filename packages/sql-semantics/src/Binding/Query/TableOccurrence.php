<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Query\Optimization\IndexDirective;
use SqlSemantics\Model\Query\Optimization\IndexedBy;
use SqlSemantics\Model\Query\Optimization\NotIndexed;
use SqlSemantics\Model\Query\Sampling\SamplingMethod;
use SqlSemantics\Model\Query\Sampling\TableSample;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Schema\TableDefinition;

/**
 * Retains PostgreSQL's descendant exclusion and the access clauses written after a table name on named query and mutation inputs.
 * @visibility SqlSemantics
 */
final class TableOccurrence
{
    /**
     * Binds the named table's row scope independently of its optional alias.
     * @param list<\SqlSemantics\Model\Query\Optimization\IndexHint> $indexHints MySQL index hints written after the table
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(string $id, string $scopeId, TableDefinition $declaration, QualifiedName $name, ?string $alias, Node $source, array $indexHints = [], ?NamedPartitions $partitions = null, ?IndexDirective $indexing = null, ?TableSample $sample = null): TableReference|OnlyTableReference
    {
        $relation = Tree::outer($source, ['relation_expr'])[0] ?? null;
        return $relation !== null && strtoupper($relation->tokens()[0]->text ?? '') === 'ONLY'
            ? new OnlyTableReference($id, $scopeId, $declaration, $name, $alias, $source, $sample)
            : new TableReference($id, $scopeId, $declaration, $name, $alias, $source, $indexHints, $partitions, $indexing, $sample);
    }

    /**
     * Reads a MySQL PARTITION selection; null when the clause is absent or empty.
     */
    public static function partitions(?Node $clause, Identifiers $identifiers): ?NamedPartitions
    {
        $names = $clause === null ? [] : array_map(static fn (Node $name): string => $identifiers->name($name->tokens()[0]), Tree::outer($clause, ['ident']));
        return $names === [] ? null : new NamedPartitions($names);
    }

    /**
     * Reads a SQLite INDEXED BY or NOT INDEXED directive; null when the clause is absent or empty.
     */
    public static function indexing(?Node $clause, Identifiers $identifiers): ?IndexDirective
    {
        $clause = $clause === null || $clause->name === 'indexed_by' ? $clause : Tree::child($clause, ['indexed_by']);
        if ($clause === null || !Tree::hasTokens($clause)) {
            return null;
        }
        $name = Tree::child($clause, ['nm']);
        return $name === null ? new NotIndexed() : new IndexedBy($identifiers->name($name->tokens()[0]));
    }

    /**
     * Reads a TABLESAMPLE clause; its arguments see only the enclosing query's scope, never the sampled table.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function sample(?Node $clause, Scope $scope): ?TableSample
    {
        if ($clause === null || !Tree::hasTokens($clause)) {
            return null;
        }
        $binder = new \SqlSemantics\Binding\ExpressionBinder();
        $methodNode = Tree::child($clause, ['func_name', 'sampling_method']) ?? Tree::invalid($clause, 'sampling method');
        $parts = $scope->identifiers->dialect === \SqlSemantics\Dialect::MySql ? [strtoupper(Tree::text($methodNode))] : $scope->identifiers->parts($methodNode);
        $method = count($parts) === 1 ? SamplingMethod::tryFrom(strtoupper($parts[0])) : null;
        $method = $method !== null && ($scope->identifiers->dialect === \SqlSemantics\Dialect::MySql || $parts[0] === strtolower($parts[0])) ? $method : new QualifiedName($parts);
        $arguments = array_map(static fn (Node $argument): \SqlSemantics\Model\Expression => $binder->bind($argument, $scope), Tree::outer(Tree::child($clause, ['expr_list', 'sampling_percentage']) ?? $clause, ['a_expr', 'sampling_percentage']));
        if ($method instanceof SamplingMethod && count($arguments) !== 1) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::SampleArguments, $clause);
        }
        $seed = Tree::child($clause, ['opt_repeatable_clause']);
        $seed = $seed === null ? null : Tree::child($seed, ['a_expr']);
        return new TableSample($method, $arguments, $seed === null ? null : $binder->bind($seed, $scope));
    }

    /**
     * Reads the MySQL index hints written after one table name; the KEY spelling is the INDEX synonym.
     * @return list<\SqlSemantics\Model\Query\Optimization\IndexHint>
     */
    public static function hints(Node $source, Identifiers $identifiers): array
    {
        $definition = Tree::child(Tree::child($source, ['single_table']) ?? $source, ['opt_key_definition']);
        if ($definition === null || $identifiers->dialect !== \SqlSemantics\Dialect::MySql) {
            return [];
        }
        $hints = [];
        foreach (Tree::outer($definition, ['index_hint_definition']) as $hint) {
            $clause = Tree::child($hint, ['index_hint_clause']);
            $scope = $clause === null ? '' : trim(preg_replace('/\s+/', ' ', strtoupper(substr(Tree::text($clause), 3))) ?? '');
            $indexes = array_map(static fn (Node $element): string => $identifiers->name($element->tokens()[0]), Tree::outer($hint, ['key_usage_element']));
            $hints[] = new \SqlSemantics\Model\Query\Optimization\IndexHint(\SqlSemantics\Model\Query\Optimization\IndexHintAction::from(strtoupper($hint->tokens()[0]->text)), $scope === '' ? null : \SqlSemantics\Model\Query\Optimization\IndexHintScope::from($scope), $indexes);
        }
        return $hints;
    }
    /**
     * Resolves a named relation against the current schema without reading rows.
     */
    public static function resolve(Node $node, QueryContext $context, string $scopeId): TableReference|OnlyTableReference
    {
        $name = Tree::outer($node, ['qualified_name', 'table_ident'])[0] ?? null;
        if ($name === null) {
            Tree::invalid($node, 'table occurrence name');
        }
        $parts = $context->tables->identifiers->parts($name);
        $declaration = $context->tables->resolve($parts, $name);
        $aliasNode = Tree::child($node, ['opt_table_alias']);
        $alias = $aliasNode === null ? null : $context->tables->identifiers->name($aliasNode->tokens()[count($aliasNode->tokens()) - 1]);
        return self::bind($context->ids->relation(), $scopeId, $declaration, $context->tables->name($parts, $declaration), $alias, $node, self::hints($node, $context->tables->identifiers));
    }


}
