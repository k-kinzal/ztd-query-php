<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
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
     * Establishes the list and scope conditions before publication.
     *
     * @param non-empty-list<ResultColumn> $columns The ordered result columns
     * @param TableSource|Join|null $source The resolved input occurrences
     * @param GrammarRelease $release The selected MySQL release
     */
    public function __construct(
        array $columns,
        public readonly TableSource|Join|null $source = null,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly BooleanLiteral|IntegerComparison|NullLiteral|null $where = null,
    ) {
        $results = Check::listOf($columns, ResultColumn::class, 'A SELECT needs a nonempty list of result columns.', 1);
        (new SelectBounds())->check($results, $source, $where);
        $this->columns = $results;
    }

    /**
     * Identifies the fixed MySQL language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native';
    }

    /**
     * Renders the statement from its semantic structure.
     */
    public function toString(): string
    {
        return (new SelectSql())->render($this);
    }
}
