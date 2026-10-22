<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;

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
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex, $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex, $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition => (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\IndexOrdering())->legacy($value->kind, $value->parts, $value->algorithm, $value->options) ? 3 : self::ALL,
            $value instanceof LanguageProfile => $this->bit($value->grammar),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation && (($value->left instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select && $value->left->limit !== null) || ($value->left instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody && $value->left->selection->limit !== null)) => 1,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion && ($value->right instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody || $value->right->limit !== null) => 1,
            $value instanceof Explain, $value instanceof DeferredExplain => $this->explanation($value),
            $value === \SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Vector => 384,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select && $value->qualify !== null => 480,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample => 448,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select, $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression, $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation, $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal => $this->ordering($value),
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
    public function explanation(Explain|DeferredExplain $value): int
    {
        $domain = $value->modifier === null ? self::ALL : 3;
        if ($value->analyze || strcasecmp($value->format->value ?? '', 'TREE') === 0) {
            $domain &= 508;
        }
        if ($value->database !== null) {
            $domain &= 496;
        }
        if ($value->emptyInto) {
            $domain &= 48;
        }
        if ($value->into !== null) {
            $domain &= $value instanceof DeferredExplain && !(new ExplainFacts())->target($value->into) ? ($value->into->name->value === '' ? 8 : 56) : 504;
        }
        return $domain;
    }
}
