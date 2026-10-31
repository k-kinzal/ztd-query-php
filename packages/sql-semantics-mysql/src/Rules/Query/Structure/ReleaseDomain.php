<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;

/**
 * Intersects release-specific interpretations retained in one semantic graph.
 *
 * An ignored empty EXPLAIN destination means no assignment only in 8.2–8.3.
 * An illegal destination survives routine definition only in 8.1–8.3. These
 * meanings cannot coexist with later-only operands such as VECTOR. This checks
 * language composition, independently of execution and catalog availability.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReleaseDomain
{
    /**
     * All nine supported MySQL grammar releases, in chronological bit order.
     */
    public const ALL = 511;

    /**
     * Identifies one supported MySQL language, or zero for another dialect.
     */
    public function bit(GrammarRelease $release): int
    {
        return match ($release) {
            GrammarRelease::MySql5651 => 1,
            GrammarRelease::MySql5744 => 2,
            GrammarRelease::MySql8044 => 4,
            GrammarRelease::MySql810 => 8,
            GrammarRelease::MySql820 => 16,
            GrammarRelease::MySql830 => 32,
            GrammarRelease::MySql847 => 64,
            GrammarRelease::MySql901 => 128,
            GrammarRelease::MySql910 => 256,
            GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172, GrammarRelease::Sqlite3472 => 0,
        };
    }

    /**
     * Returns the release domain of a node whose interpretation crosses modern releases.
     */
    public function of(object $value): int
    {
        return match (true) {
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod, $value instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod, $value instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod => (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions())->releases($value->expression),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex, $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition => (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\IndexOrdering())->legacy($value->kind, $value->parts, $value->algorithm, $value->options) ? 3 : self::ALL,
            $value instanceof LanguageProfile => $this->bit($value->grammar),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation => $this->operand($value->right) & (($value->left instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select && $value->left->limit !== null) || ($value->left instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody && $value->left->selection->limit !== null) ? 1 : self::ALL),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion => $this->operand($value->right) & ($value->right instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody || $value->right->limit !== null ? 1 : self::ALL),
            $value instanceof Explain, $value instanceof DeferredExplain, $value instanceof ExplainConnection => $this->explanation($value),
            $value === \SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Vector => 384,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select && $value->qualify !== null => 480 & $this->modifiers($value->options),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample => 448,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select => $this->ordering($value) & $this->modifiers($value->options),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection => $this->modifiers($value->options),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation => $this->ordering($value) & $this->operand($value->right),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable => $this->operand($value->query),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery, $value instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery, $value instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists, $value instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery, $value instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison, $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubquery, $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate => $this->subquery($value->query),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression, $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal => $this->ordering($value),
            default => $this->handler($value),
        };
    }

    /**
     * Keeps a query block whose query cache modifiers conflict out of the legacy releases that refuse the pair (SelectOptions).
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption> $options The modifiers in written order
     */
    public function modifiers(array $options): int
    {
        $domain = self::ALL;
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744] as $release) {
            if ((new \SqlSemantics\Platform\MySql\Rules\Query\SelectOptions())->conflict($options, $release) !== null) {
                $domain &= ~$this->bit($release);
            }
        }

        return $domain;
    }

    /**
     * Keeps a later set operand or a derived query with SQL_NO_CACHE in the releases that accept the modifier outside the first block of a statement: MySQL 8.0 and later.
     */
    public function operand(\SqlSemantics\Statement\Query $query): int
    {
        foreach ((new DerivedOptions())->blocks($query) as $block) {
            if (in_array(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache, $block->options, true)) {
                return self::ALL & ~3;
            }
        }

        return self::ALL;
    }

    /**
     * Keeps an expression subquery that combines ALL and DISTINCT in one block in MySQL 5.6, the only release that accepts the pair there, and one with SQL_NO_CACHE in the releases whose grammar admits the modifier in a subquery.
     */
    public function subquery(\SqlSemantics\Statement\Query $query): int
    {
        return $this->operand($query) & ((new StatementQuery())->problem($query) === null ? self::ALL : 1);
    }

    /**
     * Restricts handler reads to releases where every call has a native interpretation.
     */
    public function handler(object $value): int
    {
        return match (true) {
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek => (new \SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerOperands())->releases([$value]),
            default => self::ALL,
        };
    }

    /**
     * Keeps scalar boolean sort operands in releases where they are constants.
     */
    public function ordering(\SqlSemantics\Platform\MySql\Statement\Query\Select|\SqlSemantics\Platform\MySql\Statement\Query\QueryExpression|\SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation|\SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal $query): int
    {
        if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal) {
            return $query->literal() instanceof \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral ? 3 : self::ALL;
        }
        $groups = $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select ? ($query->groupBy->items ?? []) : [];
        $late = $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select ? ($query->late->orderBy ?? []) : [];
        foreach ([...$groups, ...$query->orderBy, ...$late] as $item) {
            if ((new \SqlSemantics\Platform\MySql\Rules\Query\SortScopes())->position($item->expression, true) instanceof \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral) {
                return 508;
            }
        }
        return self::ALL;
    }

    /**
     * Keeps ignored destinations and late target validation in the releases that give them that meaning.
     */
    public function explanation(Explain|DeferredExplain|ExplainConnection $value): int
    {
        $domain = $value->modifier === null ? self::ALL : 3;
        if ($value->analyze) {
            $domain &= 508;
        }
        if (!$value instanceof ExplainConnection && $value->database !== null) {
            $domain &= 496;
        }
        if ($value->emptyInto) {
            $domain &= 48;
        }
        if ($value->into !== null) {
            $domain &= $value instanceof DeferredExplain && !(new ExplainFacts())->target($value->into) ? ($value->into->name->value === '' ? 8 : 56) : 504;
        }
        if ($value instanceof ExplainConnection && $value->into?->name->value === '') {
            $domain &= 456;
        }
        return $domain;
    }
}
