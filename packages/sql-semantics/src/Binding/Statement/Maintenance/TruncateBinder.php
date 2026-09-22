<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\IdentityReset;
use SqlSemantics\Model\Maintenance\ReferencingTables;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement;
use SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds whole-table removal requests without executing or expanding their effects.
 * @visibility SqlSemantics
 */
final class TruncateBinder
{
    /**
     * Separates one-table MySQL truncation from PostgreSQL's sequence and reference policies.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        if (strtoupper($statement->tokens()[0]->text ?? '') !== 'TRUNCATE') {
            return null;
        }
        if ($origin->dialect === Dialect::PostgreSql) {
            $tables = array_map(static fn (Node $node): TableReference|OnlyTableReference => TableOccurrence::resolve($node, $context, $origin->scopeId), Tree::outer($statement, ['relation_expr']));
            $identities = Tree::child($statement, ['opt_restart_seqs']);
            $references = Tree::child($statement, ['opt_drop_behavior']);
            return new TruncateRelationsStatement($origin, $tables, $identities === null ? IdentityReset::Continue : IdentityReset::from(strtoupper(Tree::text($identities))), $references === null ? ReferencingTables::RequireListed : ReferencingTables::from(strtoupper(Tree::text($references))));
        }
        $table = TableOccurrence::resolve($statement, $context, $origin->scopeId);
        if (!$table instanceof TableReference) {
            throw new UnclassifiedSql('A MySQL truncation requires a physical table reference.');
        }
        return new TruncateTableStatement($origin, $table);
    }
}
