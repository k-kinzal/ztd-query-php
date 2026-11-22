<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
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
     * Establishes the list and scope conditions before publication.
     *
     * @param non-empty-list<ResultColumn> $columns The ordered result columns
     * @param TableSource|Join|null $source The resolved input occurrences
     * @param GrammarRelease $release The selected PostgreSQL release
     */
    public function __construct(
        array $columns,
        public readonly TableSource|Join|null $source = null,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        public readonly BooleanLiteral|IntegerComparison|NullLiteral|null $where = null,
    ) {
        $results = Check::listOf($columns, ResultColumn::class, 'A SELECT needs a nonempty list of result columns.', 1);
        (new SelectBounds())->check($results, $source, $where);
        $this->columns = $results;
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
