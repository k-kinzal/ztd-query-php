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
final class NullVariableStorageTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $histories = [
            'absent' => '',
            'utf8 string' => "SET @x=_utf8mb4'abc';",
            'latin1 string' => "SET @x=_latin1'abc';",
            'integer' => 'SET @x=12;',
            'decimal' => 'SET @x=1.5;',
            'double' => 'SET @x=1e0;',
            'unsigned' => 'SET @x=CAST(1 AS UNSIGNED);',
            'untyped null' => 'SET @x=NULL;',
            'numeric after latin1' => "SET @x=_latin1'abc';SET @x=12;",
            'changed connection' => 'SET @x=12;SET NAMES latin1;',
            'null integer after latin1' => "SET @x=_latin1'abc';SET @x=CAST(NULL AS SIGNED);",
            'null decimal after latin1' => "SET @x=_latin1'abc';SET @x=CAST(NULL AS DECIMAL(5,2));",
            'null initialized in latin1' => 'SET NAMES latin1;SET @x=NULL;SET NAMES utf8mb4;',
            'null integer initialized in latin1' => 'SET NAMES latin1;SET @x=CAST(NULL AS SIGNED);SET NAMES utf8mb4;',
            'numeric after null' => 'SET @x=NULL;SET @x=12;',
        ];
        foreach ($histories as $name => $history) {
            foreach (['NULL', 'CAST(NULL AS CHAR CHARACTER SET latin1)', 'CAST(NULL AS SIGNED)', 'NULLIF(1,1)', "IF(1,NULL,'a')", 'CAST(NULL AS DATE)'] as $null) {
                foreach (['SET @x=', 'SELECT @x:='] as $assignment) {
                    yield $name . ' ' . $assignment . $null => ['SET NAMES utf8mb4;' . $history . $assignment . $null . ';SELECT @x,CHARSET(@x),COLLATION(@x),COERCIBILITY(@x)'];
                }
            }
        }
        foreach (['12', "_latin1'abc'", 'NULL', 'CAST(NULL AS SIGNED)', 'CAST(NULL AS DATE)', 'CAST(NULL AS CHAR CHARACTER SET latin1)'] as $value) {
            foreach (['WHERE 0', 'LIMIT 0'] as $empty) {
                yield 'allocated ' . $value . ' ' . $empty => ['SET NAMES utf8mb4;SELECT @x:=' . $value . ' ' . $empty . ';SELECT @x,CHARSET(@x),COLLATION(@x);SET @x=CAST(NULL AS CHAR CHARACTER SET ascii);SELECT @x,CHARSET(@x),COLLATION(@x)'];
            }
        }
        foreach (["collation_connection='latin1_swedish_ci',@x=12", "@x=12,collation_connection='latin1_swedish_ci'", "collation_connection='latin1_swedish_ci',@x='abc'", "collation_connection='latin1_swedish_ci',@x=CAST(NULL AS SIGNED)", "collation_connection='latin1_swedish_ci',@x=(SELECT 12)"] as $assignments) {
            yield 'set order ' . $assignments => ['SET NAMES utf8mb4;SET ' . $assignments . ';SET @x=CAST(NULL AS CHAR CHARACTER SET ascii);SELECT @x,CHARSET(@x),COLLATION(@x),@@collation_connection'];
        }
        foreach (['@x=1,@y=IFNULL(@x,2)', '@x=1,@y=@x', "@x=_latin1'a',@y=IFNULL(@x,'b')", '@x=NULL,@y=IFNULL(@x,2)', '@x=1,@y=(@z:=IFNULL(@x,2))'] as $assignments) {
            yield 'set references ' . $assignments => ['SET NAMES utf8mb4;SET ' . $assignments . ';SELECT @x,@y,CHARSET(@y),COLLATION(@y)'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testStoredNullTypesAndAssignmentCollationsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
