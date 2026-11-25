<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\Expression\Choice\CaseLayout;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\From\DerivedSource;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\Ordering\Limit;
use SqlSemantics\Platform\MySql\Ir\Ordering\Order;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete MySQL projection over an optional external source or joined source tree.
 *
 * @visibility public
 * @example Constructing a projection directly
 *     $column = new \SqlSemantics\Platform\MySql\Ir\ResultColumn(new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('42'), new \SqlSemantics\Platform\MySql\Ir\Identifier('answer'));
 *     (new \SqlSemantics\Platform\MySql\Ir\Select([$column]))->toString() // => 'SELECT 42 AS `answer`'
 */
final class Select implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ResultColumn> The ordered result columns
     */
    public readonly array $columns;

    /**
     * @var list<Order> The precedence of positional result keys
     */
    public readonly array $orderBy;

    /**
     * Establishes the list and scope conditions before publication.
     *
     * @param non-empty-list<ResultColumn> $columns The ordered result columns
     * @param TableSource|Join|null $source The resolved input occurrences
     * @param list<Order> $orderBy The checked order keys
     * @param GrammarRelease $release The selected MySQL release
     */
    public function __construct(
        array $columns,
        public readonly TableSource|Join|null $source = null,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|null $where = null,
        public readonly bool $distinct = false,
        array $orderBy = [],
        public readonly ?Limit $limit = null,
    ) {
        $results = Check::listOf($columns, ResultColumn::class, 'A SELECT needs a nonempty list of result columns.', 1);
        (new SelectBounds())->check($results, $source, $where);
        if ($source instanceof Join) {
            foreach ($source->inputs() as $input) {
                Check::input(!$input instanceof DerivedSource || $input->definition->release === $release, 'A derived input must use its owning SELECT release.');
            }
        }
        $this->columns = $results;
        $this->orderBy = Check::listOf($orderBy, Order::class, 'ORDER BY needs an owned list of output keys.');
        Check::input(count($this->orderBy) <= 1024, 'A SELECT can have at most 1024 order keys in this profile.');
        foreach ($this->orderBy as $key) {
            Check::input($key->position <= count($results), 'An output order position must belong to the projection.');
        }
    }

    /**
     * Gives the native output heading for a signed CASE at the zero-based result position.
     * @return array{storage:IntegerStorage|null,nullable:bool}|null
     */
    public function signedCaseLayout(int $position): ?array
    {
        $expression = $this->columns[$position]->expression ?? null;

        return $expression instanceof SignedCase ? (new CaseLayout())->resolve($expression, $this->release, $this->source) : null;
    }

    /**
     * Identifies the fixed MySQL language profile.
     */
    public function profileId(): string
    {
        $optimization = $this->release === GrammarRelease::MySql5651 ? 'materialized' : 'derived-merge=on';

        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/' . $optimization;
    }

    /**
     * Renders the statement from its semantic structure.
     */
    public function toString(): string
    {
        return (new SelectSql())->render($this);
    }
}
