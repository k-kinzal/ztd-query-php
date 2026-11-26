<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Key;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An ordinary ordered InnoDB primary key.
 *
 * @visibility public
 * @example Preserving order in a compound key
 *     $term = new \SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKeyColumn(2);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKey([$term]))->terms[0]->slot // => 2
 */
final class PrimaryKey
{
    use Snapshot;

    /**
     * @var non-empty-list<PrimaryKeyColumn>
     */
    public readonly array $terms;

    /**
     * Owns the ordered nonrepeating column positions.
     *
     * @param list<PrimaryKeyColumn> $terms The resolved key fields
     */
    public function __construct(array $terms)
    {
        $checked = Check::listOf($terms, PrimaryKeyColumn::class, 'A primary key needs a nonempty ordered term list.', 1);
        Check::input(count($checked) <= 16, 'A primary key can have at most 16 column positions.');
        $seen = [];
        foreach ($checked as $term) {
            Check::input(!isset($seen[$term->slot]), 'A primary key cannot repeat a column position.');
            $seen[$term->slot] = true;
        }
        $this->terms = $checked;
    }
}
