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
final class DiagnosticLimitsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
            'stored and raised warnings' => "SELECT 'a'+1,'b'+1; GET DIAGNOSTICS @n=NUMBER; SHOW COUNT(*) WARNINGS; SHOW WARNINGS; SELECT @n",
            'repeated warnings' => "SELECT BENCHMARK(5,CAST('bad' AS UNSIGNED)); SHOW COUNT(*) WARNINGS; SHOW WARNINGS",
            'ordinary error' => 'SELECT missing_column',
            'signal error' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='original'",
            'resignal' => "CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL; SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='original'; END; CALL p()",
            'resignal set' => "CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL SET MESSAGE_TEXT='replacement',MYSQL_ERRNO=1234; SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='original'; END; CALL p()",
            'resignal after another statement' => "CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN SET @handled=1; RESIGNAL; END; SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='original'; END; CALL p()",
            'resignal after warning and error' => "CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL; SELECT CAST('1a' AS SIGNED),JSON_EXTRACT('invalid','$'); END; CALL p()",
            'continue after a partial result' => "SET @handled=0; CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @handled=1; SELECT JSON_EXTRACT('invalid','$'); SELECT @handled; END; CALL p()",
            'unhandled partial result' => "CREATE PROCEDURE p() SELECT JSON_EXTRACT('invalid','$'); CALL p()",
            'rows before a failed row' => "CREATE PROCEDURE p() SELECT JSON_EXTRACT(v,'$') FROM (SELECT '1' AS v UNION ALL SELECT 'invalid') AS t; CALL p()",
            'handled rows before a failed row' => "CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @handled=1; SELECT JSON_EXTRACT(v,'$') FROM (SELECT '1' AS v UNION ALL SELECT 'invalid') AS t; END; CALL p()",
            'warning handler' => "SET @handled=0; CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLWARNING SET @handled=1; SIGNAL SQLSTATE '01000'; END; CALL p(); SELECT @handled",
        ];
        foreach ([0, 1, 2] as $limit) {
            foreach ($statements as $name => $sql) {
                yield $name . ' limit ' . $limit => ['SET max_error_count=' . $limit . '; ' . $sql];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testBoundedConditionsRetainTotalsAndHandledErrors(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
