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
final class PartitionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerExpressions(): iterable
    {
        foreach (['USER()', '(USER())', 'CURRENT_USER', 'NOW()', 'RAND()', 'DATABASE()', 'VERSION()', 'ABS(1)', 'SIN(a)', 'CONCAT(a,1)', 'custom_function(a)', '@a', '@@sql_mode', 'FOUND_ROWS()', 'LAST_INSERT_ID()', 'UNIX_TIMESTAMP()', 'UNIX_TIMESTAMP(a)', 'a + RAND()', '@@unknown_system_variable', 'RAND(1,2)', 'USER(1)', 'ABS()'] as $expression) {
            yield $expression => ["ALTER TABLE missing PARTITION BY HASH ($expression)"];
        }
        yield 'range before missing partitions' => ['ALTER TABLE missing PARTITION BY RANGE ((USER()))'];
        yield 'list before missing partitions' => ['ALTER TABLE missing PARTITION BY LIST ((USER()))'];
        yield 'subpartition before target lookup' => ['ALTER TABLE missing PARTITION BY KEY () SUBPARTITION BY HASH ((USER())) SUBPARTITIONS 1'];
        yield 'range bound' => ['ALTER TABLE missing ADD PARTITION (PARTITION p VALUES LESS THAN (((USER()))))'];
        yield 'list bound' => ['ALTER TABLE missing ADD PARTITION (PARTITION p VALUES IN (1,((USER()))))'];
        yield 'create expression' => ['CREATE TABLE partition_check (a INT) PARTITION BY HASH ((USER()))'];
        yield 'original spaces and comment' => ["ALTER TABLE missing\nPARTITION BY HASH ( /* marker */ (USER ( )))"];
        yield 'comment after expression' => ['ALTER TABLE missing PARTITION BY HASH (USER() /* after */  )'];
        yield 'newline after expression' => ["ALTER TABLE missing PARTITION BY HASH (USER()\n )"];
        yield 'tail retains following statements'  => ['ALTER TABLE missing PARTITION BY HASH (USER()); SELECT 1'];
    }

    #[DataProvider('providerExpressions')]
    public function testPartitionResolutionMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);
        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
