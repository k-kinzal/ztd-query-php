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
        foreach (["_utf8mb4'ab' COLLATE utf8mb4_bin", "_{$mb3}'ab' COLLATE {$mb3}_bin", "_utf8mb4'aÉßΩŒ😀' COLLATE utf8mb4_general_ci", "_utf8mb4'ab' COLLATE utf8mb4_0900_bin", "_utf8mb4 X'C3A9' COLLATE utf8mb4_bin", "_utf8mb4 B'1100001110101001' COLLATE utf8mb4_bin", "_binary'ab'", "_latin1'ab'", 'USER()', '1', '1.2', 'NULL'] as $value) {
            foreach (['', ' AS CHAR(1)', ' AS CHAR(8)', ' AS BINARY(1)', ',1,1,1', ',0,4,64', ',5,3,0', ',0,4,128'] as $options) {
                yield $value . $options => ['SELECT WEIGHT_STRING(' . $value . $options . ') AS w'];
            }
        }
        foreach (['', ' AS CHAR(1)', ',1,1,1'] as $options) {
            yield 'JSON argument ' . $options => ['KILL (USER() = (USER() MEMBER OF (WEIGHT_STRING(USER()' . $options . ')))) IS TRUE'];
        }
        foreach (["_binary X'007FFF'", "_latin1'aB'", "_utf8mb4'aÉ' COLLATE utf8mb4_general_ci", "_utf8mb4'aÉ' COLLATE utf8mb4_bin", "_utf8mb4'aÉ' COLLATE utf8mb4_unicode_ci", "_utf8mb4'aÉ' COLLATE utf8mb4_unicode_520_ci"] as $value) {
            foreach (['1', '0', '6', '1 DESC', '1 REVERSE', '1 DESC REVERSE', '2 DESC', '2 REVERSE', '1, 2 DESC', '1 DESC, 2 ASC', '1 REVERSE, 2 DESC', '0 DESC', '0 REVERSE', '2-1', '0-6'] as $levels) {
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnicodeStatements(): iterable
    {
        foreach (['', 'aÉßΩŒ😀', 'l·', "и\u{0306}", "a\u{0301}", "\u{FDFA}", "\u{FDFA}a", '一가', "\u{D6FF}\u{D700}\u{D7AF}\u{18AFF}\u{10FFFF}", "\u{0}\u{301}\u{FE0F}"] as $text) {
            foreach (['utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_unicode_520_ci', 'utf8mb4_unicode_ci'] as $collation) {
                $value = "_utf8mb4 X'" . bin2hex($text) . "' COLLATE " . $collation;
                foreach (['', ' AS CHAR(1)', ' AS CHAR(8)', ',0,4,64', ',0,4,128'] as $options) {
                    yield $collation . bin2hex($text) . $options => ['SELECT WEIGHT_STRING(' . $value . $options . ') AS w; SHOW WARNINGS'];
                }
            }
        }
    }

    #[DataProvider('providerUnicodeStatements')]
    public function testUnicodeWeightsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
