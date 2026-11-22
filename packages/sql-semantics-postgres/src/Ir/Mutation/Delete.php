<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete PostgreSQL DELETE from one resolved table.
 *
 * @visibility public
 * @example Analyzing an unfiltered delete
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE public.accounts (id INTEGER)']);
 *     $semantics->analyze('DELETE FROM public.accounts', $catalog)->toString() // => 'DELETE FROM "public"."accounts"'
 */
final class Delete implements SemanticsIR
{
    use Snapshot;

    /**
     * Closes the filter over the target occurrence.
     */
    public function __construct(
        public readonly TableSource $target,
        public readonly BooleanLiteral|IntegerComparison|LogicalPredicate|NullLiteral|NullTest|null $where = null,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        (new OccurrenceScope())->predicate($where, [$target], 'A DELETE predicate slot must belong to its target.');
    }

    /**
     * Identifies the native profile used for the complete value.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Renders one absolute target and the closed predicate, when present.
     */
    public function toString(): string
    {
        $sql = 'DELETE FROM ' . ($this->target->only ? 'ONLY ' : '') . $this->target->schema->quoted() . '.' . $this->target->table->quoted();
        if ($this->where === null) {
            return $sql;
        }
        return $sql . ' WHERE ' . (new SelectSql())->predicate($this->where, $this->target, qualify: false);
    }
}
