<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A name several visible slots have at the same precedence; none is chosen.
 *
 * @visibility SqlSemantics
 */
final class AmbiguousColumn implements Resolution, Diagnostic
{
    use Snapshot;

    /**
     * @var list<ResolvedColumn> The equally ranked candidates
     */
    public readonly array $candidates;

    /**
     * @param Name $name The column name
     * @param list<ResolvedColumn> $candidates The equally ranked candidates; at least two
     */
    public function __construct(public readonly Name $name, array $candidates)
    {
        $this->candidates = Check::listOf($candidates, ResolvedColumn::class, 'An ambiguous column has at least two candidates.', 2);
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column ' . $this->name->value . ' is ambiguous.';
    }
}
