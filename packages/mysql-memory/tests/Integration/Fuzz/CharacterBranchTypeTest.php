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
final class CharacterBranchTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['COALESCE(c,1)', 'IFNULL(c,1)', 'IF(1,c,1)', 'CASE WHEN 1 THEN c ELSE 1 END', "COALESCE(c,'x')", 'COALESCE(c,v)', 'COALESCE(c,NULL)', 'COALESCE(c,1e0)', 'COALESCE(v,1e0)', 'COALESCE(c,1.2345e0)', 'IFNULL(c,1e0)', 'IF(1,c,1e0)', 'CASE WHEN 1 THEN c ELSE 1e0 END', 'COALESCE(c,1.2)', "COALESCE(c,CAST('2020-01-02' AS DATE))", "COALESCE(c,X'01')"] as $expression) {
            yield $expression => ["CREATE TABLE branch_char(c CHAR(3),v VARCHAR(3)); INSERT INTO branch_char VALUES('a','a'); SELECT " . $expression . ' AS x FROM branch_char'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testCharacterBranchesKeepTheirFieldTypeUntilVarcharParticipates(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
