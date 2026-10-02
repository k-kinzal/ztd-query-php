<?php

declare(strict_types=1);

namespace SqlSemantics\Resolution;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Resolves a column name through the relation occurrences of an environment and its enclosing positions.
 *
 * Rule: CORE-COLUMN-LOOKUP-001. Positions are searched from the innermost
 * outwards. At one position every visible occurrence the qualifier admits is
 * searched; an unqualified name skips hidden slots. Exactly one slot resolves
 * when no occurrence at that or a nearer position is incompletely known;
 * several slots at one position are ambiguous. While a nearer occurrence is
 * incompletely known, a farther known slot is only a candidate and the result
 * is conditional on the missing inputs. A name found nowhere is missing only
 * if every searched occurrence is completely known. Terminates: the chain of
 * enclosing positions and each relation list are finite. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ColumnLookup
{
    /**
     * Resolves a column name written with an optional relation qualifier.
     */
    public function find(Environment $environment, Name $column, ?QualifiedName $qualifier = null): Resolution
    {
        $open = [];
        $depth = 0;
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            $level = new LookupLevel($scope, $column, $qualifier, $depth);
            $found = $level->found();
            if ($found !== []) {
                $open = [...$open, ...$level->open()];
                if ($open !== []) {
                    return $this->conditional($column, $found, $open);
                }

                return count($found) === 1 ? $found[0] : new AmbiguousColumn($column, $found);
            }
            $open = [...$open, ...$level->open()];
            $depth++;
        }

        return $open === [] ? new MissingColumn($column, $qualifier) : $this->conditional($column, [], $open);
    }

    /**
     * Builds the conditional outcome from known candidates and incompletely known occurrences.
     *
     * @param list<ResolvedColumn> $candidates
     * @param list<VisibleRelation> $open
     */
    public function conditional(Name $column, array $candidates, array $open): ConditionalColumn
    {
        $relations = [];
        $missing = [];
        foreach ($open as $relation) {
            $relations[] = $relation->relation;
            array_push($missing, ...$relation->shape->missing);
        }

        return new ConditionalColumn($column, $candidates, $relations, $missing);
    }
}
