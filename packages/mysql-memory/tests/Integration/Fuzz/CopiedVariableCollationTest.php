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
final class CopiedVariableCollationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['latin1', 'utf8mb4'] as $sourceCharset) {
            foreach (['latin1', 'utf8mb4'] as $connectionCharset) {
                foreach (['1', '1.5', '1e0', 'CAST(NULL AS SIGNED)', 'CAST(NULL AS DECIMAL(5,2))', 'NULLIF(1e0,1e0)', 'CAST(NULL AS CHAR CHARACTER SET latin1)', "_latin1'abc'"] as $value) {
                    foreach (['SET @copied_value=@source_value', 'SELECT @copied_value:=@source_value', 'SELECT @copied_value:=@source_value FROM DUAL WHERE 0', 'SELECT @copied_value:=@source_value LIMIT 0'] as $copy) {
                        yield $sourceCharset . ' ' . $connectionCharset . ' ' . $value . ' ' . $copy => ['SET NAMES ' . $sourceCharset . ';SET @source_value=' . $value . ';SET NAMES ' . $connectionCharset . ';' . $copy . ';SELECT @copied_value,CHARSET(@copied_value),COLLATION(@copied_value),@copied_value+0'];
                    }
                }
            }
        }
        foreach (['latin1', 'utf8mb4'] as $sourceCharset) {
            $connectionCharset = $sourceCharset === 'latin1' ? 'utf8mb4' : 'latin1';
            foreach (['1.5', 'CAST(NULL AS DECIMAL(5,2))'] as $value) {
                foreach (['(@source_value)', '(+@source_value)'] as $operand) {
                    foreach (['', ' FROM DUAL WHERE 0'] as $bounds) {
                        yield 'grouped ' . $sourceCharset . ' ' . $value . ' ' . $operand . $bounds => ['SET NAMES ' . $sourceCharset . ';SET @source_value=' . $value . ';SET NAMES ' . $connectionCharset . ';SELECT @copied_value:=' . $operand . $bounds . ';SELECT @copied_value,CHARSET(@copied_value),COLLATION(@copied_value)'];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testCopiedVariableCollationsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
