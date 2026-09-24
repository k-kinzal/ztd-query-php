<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Schema\TableDefinition;

/**
 * Retains PostgreSQL's descendant exclusion on named query and mutation inputs.
 * @visibility SqlSemantics
 */
final class TableOccurrence
{
    /**
     * Binds the named table's row scope independently of its optional alias.
     * @param list<\SqlSemantics\Model\Query\Optimization\IndexHint> $indexHints MySQL index hints written after the table
     */
    public static function bind(string $id, string $scopeId, TableDefinition $declaration, QualifiedName $name, ?string $alias, Node $source, array $indexHints = []): TableReference|OnlyTableReference
    {
        $relation = Tree::outer($source, ['relation_expr'])[0] ?? null;
        return $relation !== null && strtoupper($relation->tokens()[0]->text ?? '') === 'ONLY'
            ? new OnlyTableReference($id, $scopeId, $declaration, $name, $alias, $source)
            : new TableReference($id, $scopeId, $declaration, $name, $alias, $source, $indexHints);
    }

    /**
     * Reads the MySQL index hints written after one table name; the KEY spelling is the INDEX synonym.
     * @return list<\SqlSemantics\Model\Query\Optimization\IndexHint>
     */
    public static function hints(Node $source, \SqlSemantics\Ast\Identifiers $identifiers): array
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
