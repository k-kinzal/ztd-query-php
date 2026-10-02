<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\Analysis\ExpressionReader;
use SqlSemantics\Platform\Sqlite\Analysis\QueryReader;
use SqlSemantics\Statement\Expression\Subquery\SqliteExists;
use SqlSemantics\Statement\Expression\Subquery\SqliteInQuery;
use SqlSemantics\Statement\Expression\Subquery\SqliteScalarSubquery;
use SqlSemantics\Statement\Expression\Subquery\SqliteSubquery;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;

/**
 * Lowers query operands at their immediate expression boundary.
 * @visibility SqlSemantics
 */
final class SubqueryReader
{
    /**
     * A query inherits the namespace visible at this expression site, including visible aliases.
     */
    public function read(Node $source, Scope $scope, ExpressionReader $expressions, Scope|SqliteAliasScope $context): SqliteScalarSubquery|SqliteExists|SqliteInQuery|null
    {
        $query = Tree::child($source, ['select']);
        if ($query === null) {
            return null;
        }
        $membership = Tree::child($source, ['in_op']);
        if ($membership !== null) {
            Tree::assertChildren($source, ['expr', 'in_op', 'select'], ['(', ')']);
            $subject = Tree::child($source, ['expr']);
            assert($subject !== null, 'Query membership has a left operand.');
            $nested = (new QueryReader())->read($query, $context);
            return new SqliteInQuery($expressions->read($subject, $scope), new SqliteSubquery($scope, $nested), str_starts_with(\SqlSemantics\Statement\Identifier\Ascii::upper(Tree::text($membership)), 'NOT'));
        }
        Tree::assertChildren($source, ['select'], ['(', ')', 'EXISTS']);
        $nested = (new QueryReader())->read($query, $context);
        $body = new SqliteSubquery($scope, $nested);
        return \SqlSemantics\Statement\Identifier\Ascii::upper($source->tokens()[0]->text) === 'EXISTS' ? new SqliteExists($body) : new SqliteScalarSubquery($body);
    }
}
