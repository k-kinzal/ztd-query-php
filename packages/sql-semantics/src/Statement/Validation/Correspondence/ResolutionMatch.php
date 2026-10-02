<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Reference as R;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Validation\Check;

/**
 * Checks concrete resolution outcomes, including conditional search alternatives.
 * @visibility SqlSemantics
 */
final class ResolutionMatch
{
    /**
     * Equal text never substitutes for declaration, occurrence, or output-slot identity.
     */
    public function check(R\ResolvedColumn|R\CandidateColumn|R\MissingColumn|R\AmbiguousColumn|R\AmbiguousTable|R\NamedAlias|R\OuterLookup|TableReference $expected, R\ResolvedColumn|R\CandidateColumn|R\MissingColumn|R\AmbiguousColumn|R\AmbiguousTable|R\NamedAlias|R\OuterLookup|TableReference $actual): void
    {
        if ($expected instanceof R\ResolvedColumn) {
            Check::invariant($actual instanceof R\ResolvedColumn && $actual->relation === $expected->relation && $actual->table === $expected->table && $actual->column === $expected->column, 'A resolved column must retain its actual declaration and occurrence.');
        } elseif ($expected instanceof R\NamedAlias) {
            Check::invariant($actual instanceof R\NamedAlias && $actual->projection === $expected->projection && $actual->field === $expected->field && NamesMatch::same($expected->name, $actual->name), 'An alias resolution must retain its actual output slot.');
        } elseif ($expected instanceof R\CandidateColumn) {
            Check::invariant($actual instanceof R\CandidateColumn, 'A conditional lookup must remain conditional.');
            $this->sequence($expected->possibilities, $actual->possibilities);
        } elseif ($expected instanceof R\AmbiguousColumn) {
            Check::invariant($actual instanceof R\AmbiguousColumn, 'Ambiguous columns must not select a winner.');
            $this->sequence($expected->matches, $actual->matches);
        } elseif ($expected instanceof R\AmbiguousTable) {
            Check::invariant($actual instanceof R\AmbiguousTable && $actual->relations === $expected->relations, 'Declaration conflicts must retain their actual occurrences.');
        } elseif ($expected instanceof R\OuterLookup) {
            Check::invariant($actual instanceof R\OuterLookup && EnvironmentMatch::same($expected->scope, $actual->scope) && NamesMatch::same($expected->name, $actual->name) && NamesMatch::qualified($expected->qualifier, $actual->qualifier), 'A conditional outer lookup must retain its environment and requested name.');
            $this->check($expected->resolution, $actual->resolution);
        } else {
            Check::invariant($actual === $expected, 'Missing names and undeclared occurrences must retain their actual outcome.');
        }
    }

    /**
     * Positions and duplicate alternatives are part of the resolution contract.
     * @param list<R\ResolvedColumn|R\CandidateColumn|R\MissingColumn|R\AmbiguousColumn|R\AmbiguousTable|R\NamedAlias|R\OuterLookup|TableReference> $expected
     * @param list<R\ResolvedColumn|R\CandidateColumn|R\MissingColumn|R\AmbiguousColumn|R\AmbiguousTable|R\NamedAlias|R\OuterLookup|TableReference> $actual
     */
    public function sequence(array $expected, array $actual): void
    {
        Check::invariant(count($expected) === count($actual), 'Every resolution alternative must be retained.');
        foreach ($expected as $index => $item) {
            $this->check($item, $actual[$index]);
        }
    }
}
