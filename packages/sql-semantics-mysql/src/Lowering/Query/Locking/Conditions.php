<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Locking;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Query\Tail\LockedTables;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Preserves completed lock-scope conditions before a deferred operand binds.
 *
 * CTE visibility follows nested query scopes. Projection and predicate failures
 * cannot hide a duplicate or unknown lock target. The same semantic locking
 * rule checks ordinary queries and these input-boundary observations.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Conditions
{
    /**
     * Checks complete statements so outer CTE names are known to every inner scope.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name !== 'select_stmt') {
            return null;
        }
        foreach ((new ReachedScopes())->read($node, $lowering) as [$query, $common]) {
            $problem = $this->scope($query, $lowering, $common);
            if ($problem !== null) {
                return new ParseFailure($problem, true, NoticePhase::Contextualization);
            }
        }
        return null;
    }

    /**
     * Applies the locking clauses of one reached query scope.
     *
     * Database-qualified OF names remain with ordinary resolution because this reader
     * has no current database.
     *
     * @param list<Name> $common The visible outer common table names
     */
    public function scope(Node $node, Lowering $lowering, array $common): ?Misuse
    {
        if (array_filter($node->children, static fn ($child): bool => $child->name === 'locking_clause_list') === []) {
            return null;
        }
        try {
            [$terms, $clauses, $names, $options] = (new Scopes())->read($node, $lowering, $common);
        } catch (RejectedInput) {
            // A relation whose own meaning is refused remains with its ordinary lowering rule.
            return null;
        }
        $sources = array_values(array_filter($terms, static fn ($term): bool => $term instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableSource));
        if ((new \SqlSemantics\Platform\MySql\Rules\Dml\Update\Structure())->names($sources) !== null) {
            return null;
        }
        $derivation = new Derivation(new AnalysisContext($lowering->profile, [new Name(\SqlSemantics\Platform\MySql\Rules\SessionDatabase::UNNAMED)], complete: false));
        $rule = new LockedTables();
        $entries = $rule->inputs($derivation, $derivation->environment(), $terms, $names, $options);
        foreach ($clauses as $clause) {
            if (array_filter($clause->tables, static fn ($table): bool => $table->schema !== null) !== []) {
                return null;
            }
            [$entries, $problem] = $rule->apply($derivation, $clause, $entries);
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }
}
