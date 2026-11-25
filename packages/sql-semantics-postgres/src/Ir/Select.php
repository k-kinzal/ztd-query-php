<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\DerivedSource;
use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\Limit;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\Order;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete PostgreSQL projection over an optional external source or joined source tree.
 *
 * @visibility public
 * @example Constructing a projection directly
 *     $column = new \SqlSemantics\Platform\PostgreSql\Ir\ResultColumn(new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('42'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('answer'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Select([$column]))->toString() // => 'SELECT CAST(42 AS pg_catalog.int4) AS "answer"'
 */
final class Select implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ResultColumn> The ordered result columns
     */
    public readonly array $columns;

    /**
     * @var list<Order> The checked sort keys in precedence order
     */
    public readonly array $orderBy;

    /**
     * Establishes the list and scope conditions before publication.
     *
     * @param non-empty-list<ResultColumn> $columns The ordered result columns
     * @param TableSource|Join|null $source The resolved input occurrences
     * @param list<Order> $orderBy The checked order keys
     * @param GrammarRelease $release The selected PostgreSQL release
     */
    public function __construct(
        array $columns,
        public readonly TableSource|Join|null $source = null,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        public readonly BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TextComparison|TruthTest|null $where = null,
        public readonly bool $distinct = false,
        array $orderBy = [],
        public readonly ?Limit $limit = null,
    ) {
        $results = Check::listOf($columns, ResultColumn::class, 'A SELECT needs a nonempty list of result columns.', 1);
        Check::input(count($results) <= 1024, 'A SELECT can have at most 1024 result columns in this profile.');
        if ($source instanceof Join) {
            foreach ($source->inputs() as $input) {
                Check::input(!$input instanceof DerivedSource || $input->definition->release === $release, 'A derived input must use its owning SELECT release.');
            }
        }
        $keys = Check::listOf($orderBy, Order::class, 'ORDER BY needs an owned list of sort keys.');
        Check::input(count($keys) <= 1024, 'A SELECT can have at most 1024 order keys in this profile.');
        $expressions = [];
        foreach ($results as $result) {
            $expressions[] = $result->expression;
        }
        foreach ($keys as $key) {
            if (is_int($key->target)) {
                Check::input($key->target <= count($results), 'An output order position must belong to the projection.');
            } else {
                Check::input(!$distinct, 'A DISTINCT SELECT must order by projected positions.');
                $expressions[] = $key->target;
            }
        }
        (new SelectBounds())->check($expressions, $source, $where);
        $this->columns = $results;
        $this->orderBy = $keys;
    }

    /**
     * Identifies the fixed PostgreSQL language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Renders the statement from its semantic structure.
     */
    public function toString(): string
    {
        return (new SelectSql())->render($this);
    }
}
