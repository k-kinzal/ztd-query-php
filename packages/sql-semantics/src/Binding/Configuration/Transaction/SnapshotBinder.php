<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration\Transaction;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Configuration\Transaction as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Transaction\Configuration\Locality;

/**
 * Binds PostgreSQL snapshot identifiers and the requested SET lifetime without loading snapshots.
 * @visibility SqlSemantics
 */
final class SnapshotBinder
{
    /**
     * Reads only the explicit PostgreSQL snapshot-import production, retaining the identifier literal.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $rest, Locality $locality): ?Statement\SetTransactionSnapshotStatement
    {
        $more = Tree::child($rest, ['set_rest_more']);
        if ($more === null || array_slice(SettingTokens::words($more->tokens()), 0, 2) !== ['TRANSACTION', 'SNAPSHOT']) {
            return null;
        }
        $value = Tree::child($more, ['Sconst']);
        $literal = $value === null ? null : (new LiteralBinder(Dialect::PostgreSql))->bind($value->tokens()[0]);
        if (!$literal instanceof Literal) {
            throw new UnclassifiedSql('A transaction snapshot request requires its identifier literal.');
        }
        return new Statement\SetTransactionSnapshotStatement($origin, $literal, $locality);
    }
}
