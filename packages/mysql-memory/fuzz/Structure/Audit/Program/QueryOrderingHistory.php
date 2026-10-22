<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Program;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use mysqli;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement as M;

/**
 * Retains a read across catalog widths and a stored read across reached branches.
 */
final class QueryOrderingHistory
{
    /**
     * @param list<M\QueryRoot> $queries The unchanged catalog-dependent reads
     * @param list<M\Routine\CreateProcedure> $definitions The unchanged stored fixed-width reads
     * @return array{list<string>, list<string>, list<list<?int>>}
     */
    public function observe(Session $session, mysqli $native, array $queries, array $definitions): array
    {
        $expected = [];
        $actual = [];
        $conditions = [];
        $reference = new Reference();
        foreach ($queries as $query) {
            $sql = $reference->sql($query, $session->semantics()->profile());
            $errors = [];
            foreach ([['DROP TABLE IF EXISTS t'], ['CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(3),(1)'], ['ALTER TABLE t ADD COLUMN b INT NOT NULL DEFAULT 9'], ['DROP TABLE t']] as $steps) {
                foreach ($steps as $step) {
                    $native->query($step);
                    $session->query($step);
                }
                $session->text = 'opaque request label';
                $observation = (new Native())->observe($native, $sql);
                $errors[] = $observation['error'][0] ?? null;
                $expected[] = serialize($observation);
                $actual[] = serialize((new Memory())->observe($session, $query));
            }
            $conditions[] = $errors;
        }
        [$storedExpected, $storedActual, $storedConditions] = (new WriteOrderingHistory())->observe($session, $native, $definitions);
        return [[...$expected, ...$storedExpected], [...$actual, ...$storedActual], [...$conditions, ...$storedConditions]];
    }
}
