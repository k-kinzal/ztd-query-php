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
final class StringModeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $expressions = ["CONCAT('a')","CONCAT_WS('a','b')","LOWER('a')","UPPER('a')","QUOTE('a')","REVERSE('a')","EXPORT_SET(1,'a','b')","LTRIM('a')","RTRIM('a')","TRIM('a')","REPLACE('a','b','c')","INSERT('a',1,1,'b')","SUBSTRING('a',1)","LEFT('a',1)","RIGHT('a',1)","HEX('a')","SUBSTRING_INDEX('a','b',1)",'FORMAT(1,1)',"SOUNDEX('a')","TO_BASE64('a')","REPEAT('a',2)","LPAD('a',2,'b')","RPAD('a',2,'b')",'SPACE(1)'];
        foreach (['', 'STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES'] as $mode) {
            yield $mode => ["SET sql_mode='" . $mode . "'; SELECT " . implode(', ', $expressions)];
        }
    }

    #[DataProvider('providerStatements')]
    public function testStringModesMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
