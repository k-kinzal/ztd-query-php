<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint;

use SqlSemantics\Contract\GrammarRelease;

/**
 * The name of an optimizer hint, and the form of the arguments it takes.
 *
 * MySQL 5.7 has the join buffering, multi-range read, condition pushdown,
 * range, execution time, query block name and subquery hints; MySQL 8.0
 * adds the join order, derived table, index, index merge, skip scan, hash
 * join, resource group and SET_VAR hints. MySQL 5.6 has no hints. The names
 * are keywords of the hint comment and are matched without regard to case.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html,
 * https://dev.mysql.com/doc/refman/5.7/en/optimizer-hints.html.
 *
 * @visibility public
 * @example Reading the form of a hint
 *     \SqlSemantics\Platform\MySql\Statement\Hint\HintName::NoIndex->form() // => \SqlSemantics\Platform\MySql\Statement\Hint\HintForm::Key
 */
enum HintName: string
{
    case Bka = 'BKA';
    case NoBka = 'NO_BKA';
    case Bnl = 'BNL';
    case NoBnl = 'NO_BNL';
    case HashJoin = 'HASH_JOIN';
    case NoHashJoin = 'NO_HASH_JOIN';
    case Merge = 'MERGE';
    case NoMerge = 'NO_MERGE';
    case DerivedConditionPushdown = 'DERIVED_CONDITION_PUSHDOWN';
    case NoDerivedConditionPushdown = 'NO_DERIVED_CONDITION_PUSHDOWN';
    case JoinFixedOrder = 'JOIN_FIXED_ORDER';
    case JoinOrder = 'JOIN_ORDER';
    case JoinPrefix = 'JOIN_PREFIX';
    case JoinSuffix = 'JOIN_SUFFIX';
    case GroupIndex = 'GROUP_INDEX';
    case NoGroupIndex = 'NO_GROUP_INDEX';
    case Index = 'INDEX';
    case NoIndex = 'NO_INDEX';
    case IndexMerge = 'INDEX_MERGE';
    case NoIndexMerge = 'NO_INDEX_MERGE';
    case JoinIndex = 'JOIN_INDEX';
    case NoJoinIndex = 'NO_JOIN_INDEX';
    case Mrr = 'MRR';
    case NoMrr = 'NO_MRR';
    case NoIcp = 'NO_ICP';
    case NoRangeOptimization = 'NO_RANGE_OPTIMIZATION';
    case OrderIndex = 'ORDER_INDEX';
    case NoOrderIndex = 'NO_ORDER_INDEX';
    case SkipScan = 'SKIP_SCAN';
    case NoSkipScan = 'NO_SKIP_SCAN';
    case Semijoin = 'SEMIJOIN';
    case NoSemijoin = 'NO_SEMIJOIN';
    case Subquery = 'SUBQUERY';
    case MaxExecutionTime = 'MAX_EXECUTION_TIME';
    case ResourceGroup = 'RESOURCE_GROUP';
    case SetVar = 'SET_VAR';
    case QbName = 'QB_NAME';

    /**
     * Answers the form of the arguments the hint takes.
     */
    public function form(): HintForm
    {
        return match ($this) {
            self::Bka, self::NoBka, self::Bnl, self::NoBnl, self::HashJoin, self::NoHashJoin, self::Merge, self::NoMerge,
            self::DerivedConditionPushdown, self::NoDerivedConditionPushdown => HintForm::Table,
            self::JoinOrder, self::JoinPrefix, self::JoinSuffix => HintForm::JoinOrder,
            self::JoinFixedOrder => HintForm::FixedOrder,
            self::GroupIndex, self::NoGroupIndex, self::Index, self::NoIndex, self::IndexMerge, self::NoIndexMerge, self::JoinIndex,
            self::NoJoinIndex, self::Mrr, self::NoMrr, self::NoIcp, self::NoRangeOptimization, self::OrderIndex, self::NoOrderIndex,
            self::SkipScan, self::NoSkipScan => HintForm::Key,
            self::Semijoin, self::NoSemijoin => HintForm::Semijoin,
            self::Subquery => HintForm::Subquery,
            self::MaxExecutionTime => HintForm::ExecutionTime,
            self::ResourceGroup => HintForm::ResourceGroup,
            self::SetVar => HintForm::Variable,
            self::QbName => HintForm::BlockName,
        };
    }

    /**
     * Tells whether a release has the hint: every hint from 8.0, the hints of 5.7 there, and none in 5.6.
     *
     * @example Telling the hints of 5.7 apart
     *     [\SqlSemantics\Platform\MySql\Statement\Hint\HintName::Bka->available(\SqlSemantics\Contract\GrammarRelease::MySql5744), \SqlSemantics\Platform\MySql\Statement\Hint\HintName::SetVar->available(\SqlSemantics\Contract\GrammarRelease::MySql5744)] // => [true, false]
     */
    public function available(GrammarRelease $release): bool
    {
        if ($release === GrammarRelease::MySql5651) {
            return false;
        }

        return $release !== GrammarRelease::MySql5744 || in_array($this, [self::Bka, self::NoBka, self::Bnl, self::NoBnl, self::Mrr, self::NoMrr, self::NoIcp, self::NoRangeOptimization,
            self::Semijoin, self::NoSemijoin, self::Subquery, self::MaxExecutionTime, self::QbName], true);
    }
}
