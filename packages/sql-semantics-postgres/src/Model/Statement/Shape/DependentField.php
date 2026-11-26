<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Shape;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A field lookup that missing declarations leave undecided.
 *
 * The known fields with the name are candidates; an unexpanded star may add
 * further fields with that name, so no candidate is chosen.
 *
 * @visibility SqlSemantics
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
