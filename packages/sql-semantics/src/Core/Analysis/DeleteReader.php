<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Semantic\Scope;
use SqlSemantics\Semantic\Statement\Delete;

/**
 * Lowers a single-table deletion and rejects additional write operations.
 *
 * @visibility SqlSemantics
 */
final class DeleteReader
{
    /**
     * Retains the declarations used to resolve the target and its predicate.
     */
    public function __construct(private readonly Catalog $catalog)
    {
    }

    /**
     * Resolves the target occurrence before analyzing its predicate.
     */
    public function read(Node $statement): Delete
    {
        $dialect = $this->catalog->identifiers->dialect;
        $syntax = $dialect->platform()->syntax();
        $delete = Tree::outer($statement, $syntax->nodes('deleteStatement'))[0] ?? null;
        if ($delete === null) {
            Tree::unsupported($statement, 'DELETE statement');
        }
        Tree::assertChildren($delete, $syntax->nodes('deleteChildren'), ['DELETE', 'FROM']);
        foreach (array_merge(...array_map($delete->find(...), $syntax->nodes('deleteWrapper'))) as $wrapper) {
            Tree::assertChildren($wrapper, $syntax->nodes('deleteChildren'), ['FROM']);
        }
        $table = Tree::outer($delete, $syntax->nodes('deleteTable'))[0] ?? null;
        if ($table === null) {
            Tree::unsupported($delete, 'DELETE target');
        }
        $reader = new RelationReader($this->catalog);
        $alias = Tree::outer($delete, $syntax->nodes('deleteAlias'))[0] ?? null;
        $relation = $reader->table($table, $reader->names->parts($table), $alias);
        $scope = new Scope($dialect, $relation);
        foreach (array_merge(...array_map($delete->find(...), $syntax->nodes('deleteWhere'))) as $clause) {
            Tree::assertChildren($clause, [...$syntax->nodes('expression'), ...$syntax->nodes('deleteWhere')], ['WHERE']);
        }
        $where = Tree::outer($delete, $syntax->nodes('deleteWhere'))[0] ?? null;
        $expression = $where === null ? null : Tree::outer($where, $syntax->nodes('expression'))[0] ?? null;
        if ($where !== null && $where->tokens() !== [] && $expression === null) {
            Tree::unsupported($where, 'DELETE predicate');
        }
        return new Delete($scope, $expression === null ? null : (new ExpressionReader())->read($expression, $scope));
    }
}
