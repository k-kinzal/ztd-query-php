<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Mutation\ColumnAssignment;
use SqlSemantics\Statement\Mutation\SqliteDelete;
use SqlSemantics\Statement\Mutation\SqliteUpdate;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;

/**
 * Structures deletion and simultaneous updates without executing either operation.
 * @visibility SqlSemantics
 */
final class MutationReader
{
    /**
     * Destination columns and expressions retain their distinct scopes and shared declarations.
     */
    public function read(Node $source, Catalog $catalog): SqliteDelete|SqliteUpdate
    {
        Tree::assertChildren($source, ['xfullname', 'setlist', 'orconf', 'from', 'where_opt_ret'], ['DELETE', 'FROM', 'UPDATE', 'SET']);
        $relation = Tree::child($source, ['xfullname']);
        assert($relation !== null, 'A mutation has a destination relation.');
        $target = (new QualifiedTableReader())->read($relation, $catalog);
        $from = Tree::child($source, ['from']);
        $tables = $from === null ? [] : (new SelectReader())->tables(Tree::outer($from, ['seltablist'])[0], $catalog);
        $scope = new Scope($catalog, $target, ...$tables);
        $where = $this->predicate(Tree::child($source, ['where_opt_ret']), $scope);
        if (\SqlSemantics\Statement\Identifier\Ascii::upper($source->tokens()[0]->text) === 'DELETE') {
            return new SqliteDelete($scope, $where);
        }
        $set = Tree::child($source, ['setlist']);
        assert($set !== null, 'An update contains destination assignments.');
        $policy = Tree::child($source, ['orconf']);
        $conflict = $policy === null ? ConflictAction::Implicit : ConflictAction::from(\SqlSemantics\Statement\Identifier\Ascii::upper(Tree::text(Tree::outer($policy, ['resolvetype'])[0])));
        return new SqliteUpdate($target, $scope, $where, $conflict, ...$this->assignments($set, new Scope($catalog, $target), $scope));
    }

    /**
     * Keeps assignment order and repeated destinations without evaluating earlier assignments.
     * @return non-empty-list<ColumnAssignment>
     */
    public function assignments(Node $source, Scope $destinations, Scope $inputs): array
    {
        Tree::assertChildren($source, ['setlist', 'nm', 'expr'], [',', '=']);
        $previous = Tree::child($source, ['setlist']);
        $assignments = $previous === null ? [] : $this->assignments($previous, $destinations, $inputs);
        $name = Tree::child($source, ['nm']);
        $expression = Tree::child($source, ['expr']);
        assert($name !== null && $expression !== null, 'A scalar assignment has a destination and expression.');
        $assignments[] = new ColumnAssignment(new ColumnReference($destinations, (new IdentifierReader())->name($name)), (new ExpressionReader())->read($expression, $inputs));
        return $assignments;
    }

    /**
     * Reads row selection in the operation's own scope; unsupported result clauses remain failures.
     */
    public function predicate(?Node $source, Scope $scope): ?ScalarExpression
    {
        if ($source === null) {
            return null;
        }
        Tree::assertChildren($source, ['expr'], ['WHERE']);
        return (new ExpressionReader())->read(Tree::outer($source, ['expr'])[0], $scope);
    }
}
