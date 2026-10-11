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
final class TimestampDefaultsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $settings = str_starts_with((string) getenv('MYSQL_VERSION'), '5.6.') ? ['default' => ''] : ['OFF' => 'SET explicit_defaults_for_timestamp=OFF;', 'ON' => 'SET explicit_defaults_for_timestamp=ON;'];
        $definitions = ['a TIMESTAMP', 'a TIMESTAMP(2)', 'a TIMESTAMP NULL, b TIMESTAMP', 'a TIMESTAMP DEFAULT CURRENT_TIMESTAMP, b TIMESTAMP', 'a TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'a DATETIME NOT NULL ON UPDATE CURRENT_TIMESTAMP', 'a TIMESTAMP NULL NOT NULL', 'a TIMESTAMP NOT NULL NULL'];
        foreach ($settings as $name => $set) {
            foreach (['NO_ZERO_DATE', ''] as $mode) {
                foreach ($definitions as $definition) {
                    yield $name . ' ' . $mode . ' ' . $definition => [$set . "SET timestamp=1700000000,sql_mode='" . $mode . "'; CREATE TABLE timestamp_values(" . $definition . '); SHOW CREATE TABLE timestamp_values; INSERT INTO timestamp_values () VALUES (); SELECT * FROM timestamp_values'];
                }
            }
            yield $name . ' NULL assignment and automatic update' => [$set . "SET timestamp=1700000000,sql_mode=''; CREATE TABLE timestamp_values(id INT,a TIMESTAMP(2),b TIMESTAMP NULL); INSERT INTO timestamp_values VALUES(1,NULL,NULL); SELECT * FROM timestamp_values; SET timestamp=1700000010; UPDATE timestamp_values SET id=2; SELECT * FROM timestamp_values; SET timestamp=1700000020; UPDATE timestamp_values SET a=NULL,b=NULL; SELECT * FROM timestamp_values"];
            yield $name . ' copied automatic defaults' => [$set . "SET timestamp=1700000000,sql_mode=''; CREATE TABLE timestamp_values(id INT,a TIMESTAMP); CREATE TABLE timestamp_copy LIKE timestamp_values; SHOW CREATE TABLE timestamp_copy; INSERT INTO timestamp_copy(id) VALUES(1); SELECT * FROM timestamp_copy"];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTimestampDefaultsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
