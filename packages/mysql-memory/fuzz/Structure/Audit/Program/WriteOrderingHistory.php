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
 * Invokes unchanged stored writes across missing and populated dependencies.
 */
final class WriteOrderingHistory
{
    /**
     * @param list<M\Routine\CreateProcedure> $definitions The original definitions
     * @return array{list<string>, list<string>, list<list<?int>>}
     */
    public function observe(Session $session, mysqli $native, array $definitions): array
    {
        $expected = [];
        $actual = [];
        $conditions = [];
        $reference = new Reference();
        foreach ($definitions as $definition) {
            $session->text = 'opaque request label';
            $expected[] = serialize((new Native())->observe($native, $reference->sql($definition, $session->semantics()->profile())));
            $actual[] = serialize((new Memory())->observe($session, $definition));
            $call = new M\Dml\ProcedureCall($definition->name);
            $sql = $reference->sql($call, $session->semantics()->profile());
            $errors = [];
            foreach ([['DROP TABLE IF EXISTS t', 'SET @run=0'], ['SET @run=1'], ['CREATE TABLE t(b INT)'], ['ALTER TABLE t ADD COLUMN a INT', 'INSERT INTO t VALUES(7,9),(NULL,NULL)'], ['DROP TABLE t', 'SET @run=0']] as $steps) {
                foreach ($steps as $step) {
                    $native->query($step);
                    $session->query($step);
                }
                $session->text = 'opaque request label';
                $observation = (new Native())->observe($native, $sql);
                $errors[] = $observation['error'][0] ?? null;
                $expected[] = serialize($observation);
                $actual[] = serialize((new Memory())->observe($session, $call));
            }
            $conditions[] = $errors;
        }
        return [$expected, $actual, $conditions];
    }
}
