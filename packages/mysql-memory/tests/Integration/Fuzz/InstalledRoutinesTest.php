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
final class InstalledRoutinesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerListings(): iterable
    {
        yield 'all functions' => ['SHOW FUNCTION STATUS'];
        yield 'all procedures' => ['SHOW PROCEDURE STATUS'];
        yield 'session time zone' => ["SET time_zone='+09:00'; SHOW FUNCTION STATUS WHERE Db='sys' AND Name='version_major'"];
        yield 'negative time zone' => ["SET time_zone='-03:30'; SHOW PROCEDURE STATUS WHERE Db='sys' AND Name='table_exists'"];
        yield 'function prefix' => ["SHOW FUNCTION STATUS LIKE 'ps_thread%'"];
        yield 'security filter' => ["SHOW PROCEDURE STATUS WHERE Db='sys' AND Security_type='INVOKER'"];
        yield 'installation filter' => ["SHOW FUNCTION STATUS WHERE Created < '2027-01-01' AND Modified >= '2000-01-01'"];
        yield 'signature types' => ["SELECT ROUTINE_NAME, ROUTINE_TYPE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, DATETIME_PRECISION, CHARACTER_SET_NAME, COLLATION_NAME, DTD_IDENTIFIER, SQL_DATA_ACCESS, IS_DETERMINISTIC, SECURITY_TYPE, CREATED, LAST_ALTERED, SQL_MODE, ROUTINE_COMMENT, DEFINER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='sys' ORDER BY ROUTINE_TYPE, ROUTINE_NAME"];
        yield 'parameters and returns' => ["SELECT SPECIFIC_NAME, ORDINAL_POSITION, PARAMETER_MODE, PARAMETER_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, DATETIME_PRECISION, CHARACTER_SET_NAME, COLLATION_NAME, DTD_IDENTIFIER, ROUTINE_TYPE FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA='sys' ORDER BY ROUTINE_TYPE, SPECIFIC_NAME, ORDINAL_POSITION"];
    }

    #[DataProvider('providerListings')]
    public function testMatchesPublicMetadataWithoutImportingTheResult(string $sql): void
    {
        [$target] = Servers::shared();
        $result = $target->compare($sql);

        self::assertFalse($result->volatile, (string) $result->referenceDifference);
        self::assertNull($result->difference, (string) $result->difference);
        self::assertSame([], $result->contracts);
    }
}
