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
final class WeightStringTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $mb3 = str_starts_with((string) getenv('MYSQL_VERSION'), '5.') ? 'utf8' : 'utf8mb3';
        foreach (["_utf8mb4'ab' COLLATE utf8mb4_bin", "_{$mb3}'ab' COLLATE {$mb3}_bin", "_utf8mb4'aÉßΩŒ😀' COLLATE utf8mb4_general_ci", "_binary'ab'", "_latin1'ab'", 'USER()', '1', '1.2', 'NULL'] as $value) {
            foreach (['', ' AS CHAR(1)', ' AS CHAR(8)', ' AS BINARY(1)', ',1,1,1', ',0,4,64', ',5,3,0', ',0,4,128'] as $options) {
                yield $value . $options => ['SELECT WEIGHT_STRING(' . $value . $options . ') AS w'];
            }
        }
        foreach (['', ' AS CHAR(1)', ',1,1,1'] as $options) {
            yield 'JSON argument ' . $options => ['KILL (USER() = (USER() MEMBER OF (WEIGHT_STRING(USER()' . $options . ')))) IS TRUE'];
        }
        foreach (["_binary X'007FFF'", "_latin1'aB'", "_utf8mb4'aÉ' COLLATE utf8mb4_general_ci", "_utf8mb4'aÉ' COLLATE utf8mb4_bin"] as $value) {
            foreach (['1', '0', '6', '1 DESC', '1 REVERSE', '1 DESC REVERSE', '2 DESC', '2 REVERSE', '1, 2 DESC', '1 DESC, 2 ASC', '2-1', '0-6'] as $levels) {
                yield $value . ' LEVEL ' . $levels => ['SELECT WEIGHT_STRING(' . $value . ' LEVEL ' . $levels . ') AS w; SHOW WARNINGS'];
            }
        }
        yield 'padded descending reverse level' => ["SELECT WEIGHT_STRING(_latin1'aB' AS CHAR(4) LEVEL 1 DESC REVERSE) AS w; SHOW WARNINGS"];
    }

    #[DataProvider('providerStatements')]
    public function testWeightsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);
        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
