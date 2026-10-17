<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class RebuildClockTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function providerRebuilds(): iterable
    {
        $modes = str_starts_with((string) getenv('MYSQL_VERSION'), '5.') ? ['legacy' => 'DO 1'] : ['cached' => 'DO 1', 'uncached' => 'SET information_schema_stats_expiry=0'];
        foreach (['TRUNCATE clocked', 'ALTER TABLE clocked ALGORITHM=COPY', 'ALTER TABLE clocked MODIFY a BIGINT', 'ALTER TABLE clocked ADD b INT'] as $sql) {
            foreach ([0, 1] as $rows) {
                foreach ($modes as $name => $mode) {
                    yield $sql . ' with ' . $rows . ' rows, ' . $name => [$sql, $rows, $mode];
                }
            }
        }
    }

    #[DataProvider('providerRebuilds')]
    public function testRebuildDistinguishesRetainedAndNewUpdateTimes(string $sql, int $rows, string $mode): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $target->repair($target->memoryGuard());
        $native = $target->connect($target->native, $target->nativeUser, $target->nativePassword);
        $memory = $target->connect($target->memory, 'root', '');
        $native->exec($mode);
        $memory->exec($mode);
        $native->exec('CREATE TABLE clocked(a INT PRIMARY KEY)');
        $memory->exec('CREATE TABLE clocked(a INT PRIMARY KEY)');
        $native->exec('INSERT INTO clocked SELECT 1 FROM DUAL WHERE ' . $rows);
        $memory->exec('INSERT INTO clocked SELECT 1 FROM DUAL WHERE ' . $rows);
        $table = " FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clocked'";
        $native->exec('SET @previous=(SELECT UPDATE_TIME' . $table . ')');
        $memory->exec('SET @previous=(SELECT UPDATE_TIME' . $table . ')');
        usleep(1100000);
        $native->exec('SET @before=SYSDATE()');
        $memory->exec('SET @before=SYSDATE()');
        $native->exec($sql);
        $memory->exec($sql);
        $inspect = 'SELECT UPDATE_TIME IS NULL AS absent, UPDATE_TIME=@previous AS retained, UPDATE_TIME BETWEEN @before AND SYSDATE() AS rewritten' . $table;
        $expected = $native->query($inspect);
        $actual = $memory->query($inspect);

        self::assertNotFalse($expected);
        self::assertNotFalse($actual);
        self::assertSame($expected->fetchAll(PDO::FETCH_NUM), $actual->fetchAll(PDO::FETCH_NUM));
    }
}
