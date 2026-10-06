<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Shape;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * A field lookup that missing declarations leave undecided.
 *
 * The known fields with the name are candidates; an unexpanded star may add
 * further fields with that name, so no candidate is chosen.
 *
 * @visibility public
 * @example Looking a name up in an open shape
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT * FROM t');
 *     $operation->lookupField('a') instanceof \SqlSemantics\Statement\Shape\DependentField // => true
 */
final class DependentField implements FieldLookup
{
    use Snapshot;

    /**
     * @var list<Field> The known fields with the name
     */
    public readonly array $candidates;

    /**
     * @var non-empty-list<MissingInput> The inputs needed to decide the lookup
     */
    public readonly array $missing;

    /**
     * @param string $name The looked-up name
     * @param list<Field> $candidates The known fields with the name
     * @param list<MissingInput> $missing The inputs needed to decide the lookup; at least one
     */
    public function __construct(public readonly string $name, array $candidates, array $missing)
    {
        $this->candidates = Check::listOf($candidates, Field::class, 'A dependent lookup holds candidate fields.');
        $this->missing = Check::listOf($missing, MissingInput::class, 'A dependent lookup names its missing inputs.', 1);
    }
}
