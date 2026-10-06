<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Column;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * A name whose resolution depends on declarations missing from the context.
 *
 * The candidates are the places the name can resolve to once the missing
 * inputs are known. A known candidate in a farther scope is not chosen while a
 * nearer scope is incompletely known.
 *
 * @visibility public
 * @example Keeping the candidate occurrence of an undeclared table
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t');
 *     $resolution = $query->field('a')->resolution;
 *     [$resolution->relations[0] === $query->inputRelation(), $resolution->missing[0]->describe()] // => [true, 'the declaration of relation t']
 */
final class ConditionalColumn implements Resolution
{
    use Snapshot;

    /**
     * @var list<ResolvedColumn> The known slots the name can resolve to
     */
    public readonly array $candidates;

    /**
     * @var list<\SqlSemantics\Statement\Relation> The incompletely known occurrences that can own the name
     */
    public readonly array $relations;

    /**
     * @var non-empty-list<MissingInput> The inputs needed to decide the resolution
     */
    public readonly array $missing;

    /**
     * @param Name $name The column name
     * @param list<ResolvedColumn> $candidates The known slots the name can resolve to
     * @param list<\SqlSemantics\Statement\Relation> $relations The incompletely known occurrences that can own the name
     * @param list<MissingInput> $missing The inputs needed to decide the resolution; at least one
     */
    public function __construct(public readonly Name $name, array $candidates, array $relations, array $missing)
    {
        $this->candidates = Check::listOf($candidates, ResolvedColumn::class, 'Conditional candidates are resolved columns.');
        $this->relations = Check::listOf($relations, \SqlSemantics\Statement\Relation::class, 'Conditional owners are relation occurrences.');
        $this->missing = Check::listOf($missing, MissingInput::class, 'A conditional column names its missing inputs.', 1);
    }
}
