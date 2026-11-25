<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
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
     * @var list<ReturnedColumn> The output fields in their observed order
     */
    public readonly array $returning;

    /**
     * Closes the filter and returned fields over the target occurrence.
     *
     * @param list<ReturnedColumn> $returning The output layout. An empty layout is command-only.
     */
    public function __construct(
        public readonly TableSource $target,
        public readonly BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TextComparison|TruthTest|null $where = null,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        array $returning = [],
    ) {
        Check::input($target->kind === ExternalRelationKind::BaseTable, 'A DELETE target must be a base table.');
        (new OccurrenceScope())->predicate($where, [$target], 'A DELETE predicate slot must belong to its target.');
        $checked = Check::listOf($returning, ReturnedColumn::class, 'A DELETE needs checked RETURNING fields.');
        (new ReturningLayout())->check($target, $checked);
        $this->returning = $checked;
    }

    /**
     * Identifies the native profile used for the complete value.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Renders one absolute target, the closed predicate and the output layout.
     */
    public function toString(): string
    {
        $sql = 'DELETE FROM ' . ($this->target->only ? 'ONLY ' : '') . $this->target->schema->quoted() . '.' . $this->target->table->quoted();
        $returning = (new ReturningSql())->clause($this->target, $this->returning);
        if ($this->where === null) {
            return $sql . $returning;
        }
        return $sql . ' WHERE ' . (new SelectSql())->predicate($this->where, $this->target, qualify: false) . $returning;
    }
}
