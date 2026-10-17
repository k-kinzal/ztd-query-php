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
final class SystemTargetsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'integer null type is significant' => ['SET div_precision_increment=CAST(NULL AS UNSIGNED); SELECT @@div_precision_increment; SET div_precision_increment=DEFAULT; SET div_precision_increment=NULL'];
        yield 'boolean integer null becomes false' => ['SET autocommit=CAST(NULL AS UNSIGNED); SELECT @@autocommit; SET autocommit=1; SET autocommit=NULL'];
        yield 'saved cache does not create a running cache' => ['SET PERSIST_ONLY hot.key_cache_block_size=512; SHOW WARNINGS; SELECT VARIABLE_NAME,VARIABLE_VALUE FROM performance_schema.persisted_variables WHERE VARIABLE_NAME=\'hot.key_cache_block_size\'; RESET PERSIST hot.key_cache_block_size'];
        yield 'saved null text is empty' => ['SET PERSIST_ONLY innodb_tmpdir=NULL; SELECT VARIABLE_VALUE FROM performance_schema.persisted_variables WHERE VARIABLE_NAME=\'innodb_tmpdir\'; RESET PERSIST innodb_tmpdir'];
        yield 'session target and read' => ['SET @@SESSION.autocommit=0; SELECT @@autocommit; SET @@SESSION.autocommit=1'];
        yield 'global target and read' => ['SET @@GLOBAL.autocommit=0; SELECT @@GLOBAL.autocommit; SET @@GLOBAL.autocommit=1'];
        yield 'persist only keeps running value' => ['SET GLOBAL autocommit=1; RESET PERSIST IF EXISTS autocommit; SET @@PERSIST_ONLY.autocommit=0; SELECT @@GLOBAL.autocommit; SELECT VARIABLE_NAME,VARIABLE_VALUE FROM performance_schema.persisted_variables WHERE VARIABLE_NAME=\'autocommit\'; RESET PERSIST autocommit; SELECT @@GLOBAL.autocommit'];
        yield 'persist and reset keep independent state' => ['SET PERSIST autocommit=0; SELECT @@GLOBAL.autocommit; RESET PERSIST AUTOCOMMIT; SELECT @@GLOBAL.autocommit; SET GLOBAL autocommit=1'];
        yield 'refused assignment rolls back saved state' => ['RESET PERSIST IF EXISTS autocommit; SET PERSIST autocommit=0, max_connections=NULL; SELECT VARIABLE_NAME,VARIABLE_VALUE FROM performance_schema.persisted_variables WHERE VARIABLE_NAME=\'autocommit\'; SET GLOBAL autocommit=1'];
    }

    #[DataProvider('providerStatements')]
    public function testStatementsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
