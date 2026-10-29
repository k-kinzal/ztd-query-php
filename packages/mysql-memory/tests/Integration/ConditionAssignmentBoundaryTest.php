<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class ConditionAssignmentBoundaryTest extends TestCase
{
    #[DataProvider('providerAssignments')]
    public function testPostAnalysisExecutionReportsTheConditionWithoutRecoveringSql(string $version, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $statement = $session->analyze($sql);
        self::assertNotEmpty($statement->warnings());
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new Execution($session))->statement($statement);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerAssignments(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $version) {
            foreach (["SIGNAL SQLSTATE '45000'", 'RESIGNAL'] as $command) {
                $sql = $command . ' SET MYSQL_ERRNO=@@missing_variable, MESSAGE_TEXT=@n:=1';
                yield $version . ' earlier item ' . $command => [$version, $sql, 1193];
                yield $version . ' stored earlier item ' . $command => [$version, 'CREATE PROCEDURE d.p() BEGIN IF FALSE THEN ' . $sql . '; END IF; END', 1193];
            }
            foreach (['1' => 1064, 'ROW(1,2)' => 1064, '@@missing_variable' => 1193, 'CAST(1 AS DATETIME(7))' => 1426, "'x' COLLATE missing_collation" => 1064] as $value => $code) {
                foreach (['GET DIAGNOSTICS CONDITION @n := ' . $value . ' @m = MESSAGE_TEXT', "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @n := " . $value, 'RESIGNAL SET MESSAGE_TEXT = @n := ' . $value] as $sql) {
                    yield $version . ' ' . $sql => [$version, $sql, $code];
                    yield $version . ' stored ' . $sql => [$version, 'CREATE PROCEDURE d.p() BEGIN IF FALSE THEN ' . $sql . '; END IF; END', $code];
                }
            }
        }
        yield 'ordinary SELECT retains its contextualization phase' => ['5.7.44', 'SELECT @@missing_variable, CAST(1 AS DATETIME(7))', 1426];
        yield 'stored SELECT retains its contextualization phase' => ['5.7.44', 'CREATE PROCEDURE d.p() BEGIN IF FALSE THEN SELECT @@missing_variable, CAST(1 AS DATETIME(7)); END IF; END', 1426];
    }
}
