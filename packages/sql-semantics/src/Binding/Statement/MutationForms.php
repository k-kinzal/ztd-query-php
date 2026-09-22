<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\BoundRelation;
use SqlSemantics\Binding\Query\CteBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\Mutation;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;

/**
 * Selects mutation forms whose required inputs differ at the native type level.
 *
 * @visibility SqlSemantics
 */
final class MutationForms
{
    /**
     * @param list<TableUse> $targets
     * @param list<Assignment> $writes
     * @param list<OutputColumn> $outputs
     * @param list<Ordering> $orderBy
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function update(Origin $origin, Node $source, ?BoundRelation $input, array $targets, array $writes, ?Expression $where, array $outputs, QueryContext $context, array $orderBy, ?Expression $limit): UpdateStatement
    {
        if ($targets === [] || $writes === []) {
            throw new UnclassifiedSql('An UPDATE requires a target and assignments.');
        }
        $with = (new CteBinder())->clause($origin->source, $context);
        $words = array_map(static fn ($token): string => strtoupper($token->text), $source->tokens());
        $set = array_search('SET', $words, true);
        $prefix = $set === false ? $words : array_slice($words, 0, $set);
        $conflict = \SqlSemantics\Ast\Tree::child($source, ['orconf']);
        $resolution = $conflict === null ? null : \SqlSemantics\Ast\Tree::child($conflict, ['resolvetype']);
        $response = $resolution === null ? ConstraintResponse::Default : ConstraintResponse::from(strtoupper(\SqlSemantics\Ast\Tree::text($resolution)));
        if ($input?->relation instanceof Join) {
            if ($origin->dialect === \SqlSemantics\Dialect::MySql) {
                if ($orderBy !== [] || $limit !== null) {
                    throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::JoinedMutationPagination, $source);
                }
                return new Mutation\UpdateJoinedStatement($origin, $input->relation, $targets, $writes, $where, $outputs, $with, in_array('LOW_PRIORITY', $prefix, true), in_array('IGNORE', $prefix, true));
            }
            return new Mutation\UpdateFromStatement($origin, $targets[0], $input->relation->right, $writes, $where, $outputs, $with, $response);
        }
        return new Mutation\UpdateTableStatement($origin, $targets[0], $writes, $where, $outputs, $with, $orderBy, $limit, $response, in_array('LOW_PRIORITY', $prefix, true), $origin->dialect === \SqlSemantics\Dialect::MySql && in_array('IGNORE', $prefix, true));
    }

    /**
     * @param list<TableUse> $targets
     * @param list<OutputColumn> $outputs
     * @param list<Ordering> $orderBy
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function delete(Origin $origin, Node $source, ?BoundRelation $input, array $targets, ?Expression $where, array $outputs, QueryContext $context, array $orderBy, ?Expression $limit): DeleteStatement
    {
        if ($targets === []) {
            throw new UnclassifiedSql('A DELETE requires its target.');
        }
        $with = (new CteBinder())->clause($origin->source, $context);
        $words = array_map(static fn ($token): string => strtoupper($token->text), $source->tokens());
        $from = array_search('FROM', $words, true);
        $prefix = $from === false ? $words : array_slice($words, 0, $from);
        if ($input !== null && ($input->relation instanceof Join || ($origin->dialect === \SqlSemantics\Dialect::MySql && \SqlSemantics\Binding\Query\QueryNodes::local($source, ['table_alias_ref_list', 'table_wild_list']) !== []))) {
            if ($origin->dialect === \SqlSemantics\Dialect::MySql) {
                if ($orderBy !== [] || $limit !== null) {
                    throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::JoinedMutationPagination, $source);
                }
                return new Mutation\DeleteJoinedStatement($origin, $input->relation, \SqlSemantics\Binding\Write\DeleteTargets::tables($targets, $source), $where, $outputs, $with, in_array('LOW_PRIORITY', $prefix, true), in_array('IGNORE', $prefix, true), in_array('QUICK', $prefix, true));
            }
            return new Mutation\DeleteUsingStatement($origin, $targets[0], $input->relation->right, $where, $outputs, $with);
        }
        return new Mutation\DeleteTableStatement($origin, $targets[0], $where, $outputs, $with, $orderBy, $limit, in_array('LOW_PRIORITY', $prefix, true), $origin->dialect === \SqlSemantics\Dialect::MySql && in_array('IGNORE', $prefix, true), in_array('QUICK', $prefix, true));
    }
}
