<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class DerivedScopeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'qualified outer column' => ['SELECT id,(SELECT v FROM (SELECT t1.id AS v) d) FROM t1 ORDER BY id'];
        yield 'unqualified outer column' => ['SELECT id,(SELECT v FROM (SELECT id AS v) d) FROM t1 ORDER BY id'];
        yield 'outer column with local input' => ['SELECT id,(SELECT v FROM (SELECT t1.id AS v FROM t2 LIMIT 1) d) FROM t1 ORDER BY id'];
        yield 'nested scalar outer column' => ['SELECT id,(SELECT v FROM (SELECT (SELECT t1.id) AS v) d) FROM t1 ORDER BY id'];
        yield 'local scalar correlation' => ['SELECT v FROM (SELECT (SELECT t2.id) AS v FROM t2) d ORDER BY v'];
        yield 'nested local correlation' => ['SELECT v FROM (SELECT (SELECT (SELECT t2.id)) AS v FROM t2) d ORDER BY v'];
        yield 'routine parameters and locals' => ['CREATE PROCEDURE scope_p(IN x INT) BEGIN DECLARE y INT DEFAULT 3; SELECT * FROM (SELECT x,y) d; END; CALL scope_p(2)'];
        yield 'local shadowing' => ['CREATE PROCEDURE scope_p(IN x INT) BEGIN BEGIN DECLARE x INT DEFAULT 7; SELECT * FROM (SELECT x) d; END; END; CALL scope_p(2)'];
        yield 'routine variable with outer table' => ['CREATE PROCEDURE scope_p(IN x INT) SELECT id,(SELECT v FROM (SELECT x AS v) d) FROM t1 ORDER BY id; CALL scope_p(2)'];
        yield 'trigger rows' => ['CREATE TABLE scope_values(a INT); INSERT INTO scope_values VALUES(2); CREATE TRIGGER scope_tr BEFORE UPDATE ON scope_values FOR EACH ROW SET NEW.a=(SELECT v FROM (SELECT OLD.a+NEW.a AS v) d); UPDATE scope_values SET a=3; SELECT a FROM scope_values'];
    }

    #[DataProvider('providerStatements')]
    public function testDerivedScopesMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
