<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Maintenance\DatabaseIndexScope;
use SqlSemantics\Model\Maintenance\ReindexObjectKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Maintenance;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Distinguishes named rebuild targets from a current-database index selection.
 * @visibility SqlSemantics
 */
final class ReindexBinder
{
    /**
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, Scope $scope): Maintenance\ReindexAllStatement|Maintenance\ReindexNamedStatement|Maintenance\ReindexObjectStatement|Maintenance\ReindexDatabaseStatement
    {
        if ($origin->dialect === Dialect::Sqlite) {
            $tokens = array_slice($node->tokens(), 1);
            return $tokens === [] ? new Maintenance\ReindexAllStatement($origin) : new Maintenance\ReindexNamedStatement($origin, new QualifiedName($scope->identifiers->parts(new Node('reindex_name', 0, $tokens))));
        }
        $options = ReindexOptionsBinder::bind($node, $scope);
        $selection = Tree::child($node, ['reindex_target_all']);
        if ($selection !== null) {
            $kind = DatabaseIndexScope::from(strtoupper(Tree::text($selection)));
            if ($kind === DatabaseIndexScope::SystemTables && $options->concurrently) {
                throw new InvalidSql(InputViolation::ConcurrentSystemReindex, $node);
            }
            $name = Tree::child($node, ['opt_single_name']);
            return new Maintenance\ReindexDatabaseStatement($origin, $kind, $name === null ? null : $scope->identifiers->name($name->tokens()[0]), $options);
        }
        $name = Tree::child($node, ['qualified_name', 'name']) ?? throw new UnclassifiedSql('A named REINDEX requires its target.');
        $kindNode = Tree::child($node, ['reindex_target_relation']);
        $kind = $kindNode === null ? ReindexObjectKind::Schema : ReindexObjectKind::from(strtoupper(Tree::text($kindNode)));
        return new Maintenance\ReindexObjectStatement($origin, $kind, new QualifiedName($scope->identifiers->parts($name)), $options);
    }
}
