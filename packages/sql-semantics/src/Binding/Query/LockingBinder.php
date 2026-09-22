<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Query\Locking;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\TableUse;

/**
 * Resolves locking clauses within their owning SELECT scope.
 * @visibility SqlSemantics
 */
final class LockingBinder
{
    /**
     * @return list<Locking\RowLock>
     */
    public static function bind(Node $source, Scope $scope): array
    {
        $nodes = QueryNodes::local($source, ['for_locking_item', 'locking_clause', 'select_lock_type']);
        $locks = [];
        foreach ($nodes as $node) {
            if (!Tree::hasTokens($node)) {
                continue;
            }
            $strengthNode = Tree::child($node, ['for_locking_strength', 'lock_strength']);
            $strengthText = strtoupper(Tree::text($strengthNode ?? $node));
            $strength = Locking\LockStrength::from($strengthText === 'LOCK IN SHARE MODE' ? 'SHARE' : (str_starts_with($strengthText, 'FOR ') ? substr($strengthText, 4) : $strengthText));
            $waitNode = Tree::child($node, ['opt_nowait_or_skip', 'opt_locked_row_action']);
            $wait = Locking\LockWait::from($waitNode === null ? '' : strtoupper(Tree::text($waitNode)));
            $of = Tree::child($node, ['locked_rels_list', 'table_locking_list']);
            $targets = $of === null ? [] : Tree::outer($of, ['qualified_name', 'table_ident_opt_wild']);
            $locks[] = $targets === [] ? new Locking\AllRowLock($strength, $wait) : new Locking\NamedRowLock($strength, array_map(static fn (Node $target): TableUse|Locking\UnresolvedLockRelation => self::target($target, $scope), $targets), $wait);
        }
        return $locks;
    }

    /**
     * Resolves only the current query's FROM namespace; outer aliases are not lock targets.
     */
    public static function target(Node $source, Scope $scope): TableUse|Locking\UnresolvedLockRelation
    {
        $name = $scope->identifiers->parts($source);
        $relations = array_values(array_filter($scope->relations, static fn (TableUse $relation): bool => $scope->matches($relation, $name)));
        if (count($relations) === 1) {
            return $relations[0];
        }
        $scope->diagnostics()->report($relations === [] ? 'unknown-lock-relation' : 'ambiguous-lock-relation', 'Cannot resolve lock target: ' . implode('.', $name), $source);
        return new Locking\UnresolvedLockRelation(new QualifiedName($name));
    }
}
