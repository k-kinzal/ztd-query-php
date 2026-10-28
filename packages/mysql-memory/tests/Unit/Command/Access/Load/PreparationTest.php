<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access\Load;

use MySqlMemory\Command\Access\Load\Preparation;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;

#[CoversClass(Preparation::class)]
#[Medium]
final class PreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerConditions(): iterable
    {
        yield 'charset before database' => ['', "LOAD DATA INFILE 'f' INTO TABLE t CHARSET missing SET a=ROW(1,2)", 1115];
        yield 'database before assignment' => ['', "LOAD DATA INFILE 'f' INTO TABLE t SET a=ROW(1,2)", 1046];
        yield 'disabled client before table' => ['d', "LOAD DATA LOCAL INFILE 'f' INTO TABLE absent SET a=ROW(1,2)", 3948];
        yield 'table before assignment' => ['d', "LOAD DATA INFILE 'f' INTO TABLE absent SET a=ROW(1,2)", 1146];
        yield 'assignment name before shape' => ['d', "LOAD DATA INFILE 'f' INTO TABLE t SET a=ROW(missing,2)", 1054];
        yield 'complete row constraint' => ['d', "LOAD DATA INFILE 'f' INTO TABLE t SET a=ROW(a,2)", 1241];
        yield 'fixed input variable' => ['d', "LOAD DATA INFILE 'f' INTO TABLE t COLUMNS TERMINATED BY '' (@v)", 1409];
        yield 'assignment syntax before algorithm' => ['d', "LOAD XML INFILE 'f' INTO TABLE t COMPRESSION='x' SET a=ROW(@@missing,2)", 1193];
        yield 'algorithm before row constraint' => ['d', "LOAD XML INFILE 'f' INTO TABLE t COMPRESSION='x' SET a=ROW(a,2)", 1221];
    }

    #[DataProvider('providerConditions')]
    public function testCheckUsesPublishedBindingsAndOrderedConditions(string $database, string $sql, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->variables->database = $database;
        $load = $session->semantics()->analyze($sql, $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, new \SqlSemantics\Contract\SearchPath('d'), $session->resolution()));
        self::assertInstanceOf(LoadTable::class, $load);
        $session->text = 'unrelated invalid input';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode($code);
        (new Preparation())->check($load, $session);
    }

    public function testPrepareResolvesTheAssignmentBeforeReadingAFile(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $load = $session->analyze("LOAD DATA INFILE 'f' INTO TABLE t SET a=ROW(missing,2)");
        self::assertInstanceOf(LoadTable::class, $load);
        $session->text = 'unrelated invalid input';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("Unknown column 'missing' in 'field list'");
        (new Preparation())->check($load, $session);
    }
}
