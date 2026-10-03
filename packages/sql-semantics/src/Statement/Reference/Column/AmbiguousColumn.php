<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Column;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A name several visible slots have at the same precedence; none is chosen.
 *
 * @visibility public
 * @example Keeping every candidate of an ambiguous column
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $declarations = $semantics->context([$semantics->analyze('CREATE TABLE t (a INTEGER)'), $semantics->analyze('CREATE TABLE u (a INTEGER)')]);
 *     count($semantics->analyze('SELECT a FROM t, u', $declarations)->field('a')->resolution->candidates) // => 2
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
