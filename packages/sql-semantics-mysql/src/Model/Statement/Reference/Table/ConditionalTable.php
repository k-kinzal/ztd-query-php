<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Table;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A relation name declared in a later searched schema while an earlier schema is not completely known.
 *
 * An undeclared relation of the earlier schema would be found first, so the
 * known declaration is a candidate and not the resolution.
 *
 * @visibility SqlSemantics
 */
final class ConditionalTable implements TableResolution
{
    use Snapshot;

    /**
     * @var non-empty-list<Table> The declarations found in the later schema
     */
    public readonly array $candidates;

    /**
     * @param list<Table> $candidates The declarations found in the later schema; at least one
     * @param UndeclaredRelation $missing The possibly existing relation of an earlier schema
     */
    public function __construct(array $candidates, public readonly UndeclaredRelation $missing)
    {
        $this->candidates = Check::listOf($candidates, Table::class, 'A conditional table has at least one candidate.', 1);
    }
}
