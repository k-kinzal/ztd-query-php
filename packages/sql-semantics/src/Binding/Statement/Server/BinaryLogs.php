<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Replication as Statement;

/**
 * Binds binary log purges by log name or by cut-off time.
 * @visibility SqlSemantics
 */
final class BinaryLogs
{
    /**
     * Treats PURGE MASTER LOGS as the same request as PURGE BINARY LOGS.
     * @throws UnclassifiedSql
     */
    public static function purge(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $option = Tree::outer($node, ['purge_option'])[0] ?? throw new UnclassifiedSql('PURGE requires a TO or BEFORE clause.');
        $children = Tree::significant($option);
        $operand = $children[1] ?? throw new UnclassifiedSql('PURGE requires a TO or BEFORE operand.');
        if (strtoupper(Tree::text($children[0])) === 'TO') {
            return new Statement\PurgeBinaryLogsToStatement($origin, Literals::text($operand));
        }
        if (!$operand instanceof Node) {
            throw new UnclassifiedSql('PURGE BEFORE requires an expression.');
        }
        return new Statement\PurgeBinaryLogsBeforeStatement($origin, (new ExpressionBinder())->bind($operand, new Scope($context->tables->identifiers, queries: $context)));
    }
}
