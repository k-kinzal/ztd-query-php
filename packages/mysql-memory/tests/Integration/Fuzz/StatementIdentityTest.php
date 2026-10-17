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
final class StatementIdentityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'initial pseudo id' => ['SELECT @@pseudo_thread_id=CONNECTION_ID()'];
        yield 'assigned pseudo id' => ["SET pseudo_thread_id=42; SELECT CONNECTION_ID(), @@pseudo_thread_id; SHOW VARIABLES LIKE 'pseudo_thread_id'"];
        yield 'insert id aliases' => ['SELECT LAST_INSERT_ID(42); SELECT @@last_insert_id, @@identity; SET identity=73; SELECT LAST_INSERT_ID(), @@last_insert_id, @@identity'];
        yield 'unsigned insert id alias' => ['SET last_insert_id=18446744073709551615; SELECT LAST_INSERT_ID(), @@identity'];
        if (str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            return;
        }
        yield 'sequence across client statements' => ['SET @before=@@statement_id; SELECT @@statement_id=@before+1'];
        yield 'read only id' => ['SET statement_id=42'];
        yield 'prepared execution uses its outer id' => ["PREPARE p FROM 'SELECT @@statement_id=@before+1'; SET @before=@@statement_id; EXECUTE p"];
        yield 'table and scalar reads share the sequence' => ["SET @before=@@statement_id; SELECT CAST(VARIABLE_VALUE AS UNSIGNED)=@before+1 FROM performance_schema.session_variables WHERE VARIABLE_NAME='statement_id'"];
    }

    #[DataProvider('providerStatements')]
    public function testIdentitiesMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
