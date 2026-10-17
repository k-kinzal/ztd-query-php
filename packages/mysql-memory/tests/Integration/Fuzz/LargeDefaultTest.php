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
final class LargeDefaultTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $version = getenv('MYSQL_VERSION');
        $types = is_string($version) && str_starts_with($version, '5.6.') ? ['POINT', 'BLOB', 'TEXT'] : ['POINT', 'BLOB', 'TEXT', 'JSON'];
        foreach (['', 'STRICT_TRANS_TABLES'] as $mode) {
            foreach ($types as $type) {
                foreach (['', ' NOT NULL'] as $required) {
                    foreach (['NULL', "''", "'text'", '0'] as $value) {
                        yield $mode . ' ' . $type . $required . ' ' . $value => ["SET sql_mode='" . $mode . "'; CREATE TABLE large_defaults(a " . $type . $required . ' DEFAULT ' . $value . '); SHOW WARNINGS; SHOW CREATE TABLE large_defaults; SHOW COLUMNS FROM large_defaults; INSERT INTO large_defaults VALUES(); SHOW WARNINGS; SET sql_mode="STRICT_TRANS_TABLES"; INSERT INTO large_defaults VALUES(DEFAULT); SHOW WARNINGS; SELECT HEX(a) FROM large_defaults'];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testLargeDefaultsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
