<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Insertion\InsertDefaults;
use SqlSemantics\Statement\Insertion\InsertRows;
use SqlSemantics\Statement\Insertion\InsertSelect;
use SqlSemantics\Statement\Insertion\Target;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;

/**
 * Structures insertion targets and their distinct value, query, or default sources.
 * @visibility SqlSemantics
 */
final class InsertionReader
{
    /**
     * Target bindings never become the input scope of VALUES or SELECT expressions.
     */
    public function read(Node $source, Catalog $catalog): InsertRows|InsertSelect|InsertDefaults
    {
        Tree::assertChildren($source, ['insert_cmd', 'xfullname', 'idlist_opt', 'select'], ['INTO', 'DEFAULT', 'VALUES']);
        $command = Tree::child($source, ['insert_cmd']);
        $relation = Tree::child($source, ['xfullname']);
        assert($command !== null && $relation !== null, 'An insertion has an operation and destination.');
        $columns = Tree::child($source, ['idlist_opt']);
        $names = array_map(static fn (Node $name): Name => (new IdentifierReader())->name($name), $columns === null ? [] : Tree::outer($columns, ['nm']));
        $target = new Target($this->relation($relation, $catalog), ...$names);
        $replace = strtoupper($command->tokens()[0]->text) === 'REPLACE';
        $policy = Tree::child($command, ['orconf']);
        $conflict = $replace ? ConflictAction::Replace : ($policy === null ? ConflictAction::Implicit : ConflictAction::from(strtoupper(Tree::text(Tree::outer($policy, ['resolvetype'])[0]))));
        $query = Tree::child($source, ['select']);
        if ($query === null) {
            return new InsertDefaults($target, $conflict, $replace);
        }
        $input = (new QueryReader())->read($query, $catalog);
        return $input instanceof Rows ? new InsertRows($target, $input, $conflict, $replace) : new InsertSelect($target, $input, $conflict, $replace);
    }

    /**
     * Reads namespace positions separately from an optional destination alias.
     */
    public function relation(Node $source, Catalog $catalog): TableReference
    {
        $names = (new IdentifierReader())->directNames($source);
        $hasAlias = array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Token && $child->name === 'AS') !== [];
        $alias = $hasAlias ? array_pop($names) : null;
        assert(count($names) === 1 || count($names) === 2, 'An insertion destination has one name and an optional database.');
        return new TableReference($catalog, count($names) === 1 ? new QualifiedName($names[0]) : new QualifiedName($names[1], $names[0]), $alias);
    }
}
