<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Builds paths that wait at one block and differ only in one local value.
 * @visibility root
 */
final class JoinPaths
{
    /**
     * Builds two joinable candidates; `table` is shared and `sql` holds the given values.
     * @param Term $first Value on the first path
     * @param Term $second Value on the second path
     * @return array{State, State} Paths at block 2 entered from block 1
     */
    public static function pair(Term $first, Term $second): array
    {
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a = $base->fork();
        $b = $base->fork();
        $a->memory->write($a->local('sql'), $first);
        $b->memory->write($b->local('sql'), $second);
        return [$a, $b];
    }
}
