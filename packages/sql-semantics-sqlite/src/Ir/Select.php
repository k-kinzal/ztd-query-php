<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete SQLite projection over zero or one external table.
 *
 * @visibility public
 * @example Constructing a projection directly
 *     $column = new \SqlSemantics\Platform\Sqlite\Ir\ResultColumn(new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('42'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('answer'));
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Select([$column]))->toString() // => 'SELECT 42 AS "answer"'
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
     * @param TableSource|null $source The external input slots
     */
    public function __construct(array $columns, public readonly ?TableSource $source = null)
    {
        $results = Check::listOf($columns, ResultColumn::class, 'A SELECT needs a nonempty list of result columns.', 1);
        (new SelectBounds())->check($results, $source);
        $this->columns = $results;
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Renders the statement from its semantic structure.
     */
    public function toString(): string
    {
        return (new SelectSql())->render($this);
    }
}
