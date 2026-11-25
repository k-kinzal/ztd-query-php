<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\SelectSql;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete MySQL DELETE from one resolved table.
 *
 * @visibility public
 * @example Analyzing an unfiltered delete
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE app.accounts (id INTEGER)']);
 *     $semantics->analyze('DELETE FROM app.accounts', $catalog)->toString() // => 'DELETE FROM `app`.`accounts`'
 */
final class Delete implements SemanticsIR
{
    use Snapshot;

    /**
     * Closes the filter over the target occurrence.
     */
    public function __construct(
        public readonly TableSource $target,
        public readonly BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|null $where = null,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
    ) {
        Check::input($target->kind->isTable(), 'A DELETE target must be a base table.');
        (new OccurrenceScope())->predicate($where, [$target], 'A DELETE predicate slot must belong to its target.');
    }

    /**
     * Identifies the native profile used for the complete value.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native';
    }

    /**
     * Renders one absolute target and the closed predicate, when present.
     */
    public function toString(): string
    {
        $sql = 'DELETE FROM ' . $this->target->schema->quoted() . '.' . $this->target->table->quoted();
        if ($this->where === null) {
            return $sql;
        }
        return $sql . ' WHERE ' . (new SelectSql())->predicate($this->where, $this->target, qualify: false);
    }
}
