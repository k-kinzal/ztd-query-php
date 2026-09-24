<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Table\GeneratedColumnExpressionStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Table\PartitionSchemeStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds the MySQL 5 parser entries for stored partitioning and generated column expressions; no table is known, so column names stay unresolved.
 * @visibility SqlSemantics
 */
final class EntryPoints
{
    /**
     * Binds PARTITION BY ... or PARSE_GCOL_EXPR (expression).
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): BoundStatement
    {
        $scope = new Scope($context->tables->identifiers, queries: $context, detached: true);
        if ($statement->name === 'partition_entry') {
            return new PartitionSchemeStatement($origin, PartitionSchemes::read($statement, $scope) ?? throw new UnclassifiedSql('PARTITION BY requires its clause.'));
        }
        $expression = Tree::outer($statement, ['expr'])[0] ?? throw new UnclassifiedSql('PARSE_GCOL_EXPR requires an expression.');
        return new GeneratedColumnExpressionStatement($origin, (new ExpressionBinder())->bind($expression, $scope));
    }
}
