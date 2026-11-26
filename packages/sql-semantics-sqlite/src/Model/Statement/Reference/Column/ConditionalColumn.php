<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A name whose resolution depends on declarations missing from the context.
 *
 * The candidates are the places the name can resolve to once the missing
 * inputs are known. A known candidate in a farther scope is not chosen while a
 * nearer scope is incompletely known.
 *
 * @visibility SqlSemantics
 */
final class ConditionalColumn implements Resolution
{
    use Snapshot;

    /**
     * @var list<ResolvedColumn> The known slots the name can resolve to
     */
    public readonly array $candidates;

    /**
     * @var list<\SqlSemantics\Platform\Sqlite\Model\Statement\Relation> The incompletely known occurrences that can own the name
     */
    public readonly array $relations;

    /**
     * @var non-empty-list<MissingInput> The inputs needed to decide the resolution
     */
    public readonly array $missing;

    /**
     * @param Name $name The column name
     * @param list<ResolvedColumn> $candidates The known slots the name can resolve to
     * @param list<\SqlSemantics\Platform\Sqlite\Model\Statement\Relation> $relations The incompletely known occurrences that can own the name
     * @param list<MissingInput> $missing The inputs needed to decide the resolution; at least one
     */
    public function __construct(public readonly Name $name, array $candidates, array $relations, array $missing)
    {
        $this->candidates = Check::listOf($candidates, ResolvedColumn::class, 'Conditional candidates are resolved columns.');
        $this->relations = Check::listOf($relations, \SqlSemantics\Platform\Sqlite\Model\Statement\Relation::class, 'Conditional owners are relation occurrences.');
        $this->missing = Check::listOf($missing, MissingInput::class, 'A conditional column names its missing inputs.', 1);
    }
}
