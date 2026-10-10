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
