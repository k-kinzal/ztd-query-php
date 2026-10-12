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
final class TemporaryDefinitionOpeningTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE reopen_definition(n INT);INSERT INTO reopen_definition VALUES(1),(2);';
        foreach ([
            'CREATE PROCEDURE p() SELECT * FROM reopen_definition a JOIN reopen_definition b USING(n)',
            'CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN (SELECT COUNT(*) FROM reopen_definition a JOIN reopen_definition b)',
            'CREATE PROCEDURE p() INSERT INTO reopen_definition SELECT i.n+2 FROM reopen_definition i',
            'CREATE PROCEDURE p() SELECT * FROM reopen_definition a JOIN reopen_definition b USING(n);CALL p()',
            'CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN (SELECT COUNT(*) FROM reopen_definition a JOIN reopen_definition b);SELECT f()',
            'CREATE TABLE trigger_base(n INT);CREATE TRIGGER tr BEFORE INSERT ON trigger_base FOR EACH ROW SET NEW.n=(SELECT COUNT(*) FROM reopen_definition a JOIN reopen_definition b)',
            'CREATE TABLE trigger_base(n INT);CREATE TRIGGER tr BEFORE INSERT ON trigger_base FOR EACH ROW SET NEW.n=(SELECT COUNT(*) FROM reopen_definition a JOIN reopen_definition b);INSERT INTO trigger_base VALUES(1)',
            'CREATE EVENT e ON SCHEDULE AT \'2099-01-01 00:00:00\' DISABLE DO SELECT * FROM reopen_definition a JOIN reopen_definition b USING(n)',
            'CREATE EVENT e ON SCHEDULE EVERY 1 YEAR STARTS \'2099-01-01 00:00:00\' DISABLE DO SELECT 1;ALTER EVENT e DO SELECT * FROM reopen_definition a JOIN reopen_definition b USING(n)',
            'CREATE VIEW v AS SELECT * FROM reopen_definition a JOIN reopen_definition b USING(n)',
            'CREATE VIEW v AS SELECT * FROM reopen_definition a',
            'CREATE VIEW v AS SELECT * FROM reopen_definition',
            'CREATE VIEW v AS SELECT 1 n;ALTER VIEW v AS SELECT * FROM reopen_definition a',
            'CREATE VIEW v AS SELECT 1 n;CREATE OR REPLACE VIEW v AS SELECT * FROM reopen_definition a',
            'CREATE VIEW v AS SELECT (SELECT SUM(n) FROM reopen_definition inner_table) total FROM reopen_definition outer_table',
        ] as $query) {
            yield $query => [$setup . $query . ';SELECT 1 AS created'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTemporaryOpeningMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
