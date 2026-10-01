<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Binding\SyntaxGuard;
use SqlSemantics\Semantic\Statement\Select;

/**
 * Constructs one semantic SELECT after checking every clause.
 * @visibility SqlSemantics
 */
final class SelectReader
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(private readonly Catalog $catalog)
    {
    }

    /**
     * Lowers the supplied syntax and rejects any unmodeled semantic operation.
     */
    public function read(Node $statement): Select
    {
        $syntax = $this->catalog->identifiers->dialect->platform()->syntax();
        $selects = array_merge(...array_map($statement->find(...), $syntax->nodes('selectBody')));
        if (count($selects) !== 1) {
            Tree::unsupported($statement, 'query shape');
        }
        $select = $selects[0];
        SyntaxGuard::select($statement, $select, $syntax);
        $from = Tree::outer($select, $syntax->nodes('from'))[0] ?? null;
        $scope = (new RelationReader($this->catalog))->scope($from);
        $where = Tree::outer($select, $syntax->nodes('where'))[0] ?? null;
        $predicate = $where === null ? null : Tree::outer($where, $syntax->nodes('expression'))[0] ?? null;
        $condition = $predicate === null ? null : (new ExpressionReader())->read($predicate, $scope);
        $fields = (new ProjectionReader())->read($select, $scope);
        $distinct = false;
        foreach (Tree::outer($select, $syntax->nodes('selectOptions')) as $options) {
            $distinct = $distinct || strtoupper(Tree::text($options)) === 'DISTINCT';
        }
        $modifiers = new ModifiersReader();
        [$limit, $offset] = $modifiers->pagination($statement, $scope);
        return new Select($fields, $distinct, $condition, $modifiers->ordering($statement, $fields), $limit, $offset);
    }
}
