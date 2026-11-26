<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An empty membership decision with its surviving ordered binding registrations.
 *
 * @visibility public
 * @example Representing a decided empty NOT IN with a numbered binding
 *     $binding = new \SqlSemantics\Platform\Sqlite\Ir\Parameter('02');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership(true, [$binding]))->bindings[0]->slot // => 2
 */
final class FoldedMembership
{
    use Snapshot;

    /**
     * @var list<Parameter> The lexical binding registrations of the discarded operand
     */
    public readonly array $bindings;

    /**
     * Holds the constant decision and the ordered numbered binding registrations.
     * @param list<Parameter> $bindings The registrations in statement order
     */
    public function __construct(public readonly bool $matches, array $bindings = [])
    {
        Check::input(count($bindings) <= 8192, 'An empty membership footprint exceeds the expanded expression envelope.');
        $this->bindings = Check::listOf($bindings, Parameter::class, 'Expected an ordered list of numbered registrations.');
    }
}
