<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The resolved declaration of a table's primary key.
 *
 * @visibility public
 * @example Describing a single-column table key
 *     $column = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm(2);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey([$column]))->terms[0]->slot // => 2
 */
final class PrimaryKey
{
    use Snapshot;

    /**
     * @var non-empty-list<KeyTerm>
     */
    public readonly array $terms;

    /**
     * @param list<KeyTerm> $terms Ordered owning-column references
     * @param KeyConflict|null $conflict The explicit conflict clause, if present
     * @param bool $autoIncrement Whether the rowid alias uses persistent allocation
     * @throws InvalidConstruction When the key's terms do not fit its declaration role
     */
    public function __construct(
        array $terms,
        public readonly bool $inline = false,
        public readonly ?KeyConflict $conflict = null,
        public readonly bool $autoIncrement = false,
    ) {
        $checked = Check::listOf($terms, KeyTerm::class, 'A primary key needs a nonempty ordered term list.', 1);
        Check::input(count($checked) <= 2000, 'A primary key can have at most 2000 terms in this profile.');
        Check::input(!$inline || count($checked) === 1, 'An inline primary key needs exactly one term.');
        Check::input(!$autoIncrement || count($checked) === 1, 'AUTOINCREMENT needs exactly one key term.');
        Check::input(!$autoIncrement || !$inline || !$checked[0]->descending, 'AUTOINCREMENT requires an inline rowid alias.');
        $this->terms = $checked;
    }
}
