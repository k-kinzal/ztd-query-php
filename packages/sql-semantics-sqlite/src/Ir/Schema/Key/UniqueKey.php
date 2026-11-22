<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A UNIQUE constraint and its ordered terms.
 *
 * @visibility public
 * @example Declaring an inline uniqueness constraint on the first slot
 *     $term = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm(0);
 *     $key = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey([$term], true);
 *     $key->terms[0]->slot // => 0
 */
final class UniqueKey
{
    use Snapshot;

    /**
     * @var non-empty-list<KeyTerm>
     */
    public readonly array $terms;

    /**
     * Checks the shape of a uniqueness clause within its owning declaration.
     *
     * @param list<KeyTerm> $terms The indexed slots in declaration order
     */
    public function __construct(array $terms, public readonly bool $inline = false, public readonly ?KeyConflict $conflict = null)
    {
        $checked = Check::listOf($terms, KeyTerm::class, 'A UNIQUE constraint needs a nonempty list of key terms.', 1);
        Check::input(count($checked) <= 2000, 'A UNIQUE constraint can have at most 2000 terms.');
        Check::input(!$inline || (count($checked) === 1 && !$checked[0]->descending), 'An inline UNIQUE constraint needs one ascending term.');
        $this->terms = $checked;
    }
}
