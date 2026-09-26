<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\ShowCreateTable as Subject;
use SqlParser\MySql\MySqlParser;

#[CoversClass(Subject::class)]
final class ShowCreateTableTest extends TestCase
{
    #[DataProvider('providerTableNames')]
    public function testStatementWritesTheNameTheGrammarReads(string $tableName, string $expected): void
    {
        self::assertSame($expected, (new Subject(new MySqlParser()))->statement($tableName));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerTableNames(): array
    {
        return [
            ['users', 'SHOW CREATE TABLE users'],
            ['app.items', 'SHOW CREATE TABLE app.items'],
            ['`users`', 'SHOW CREATE TABLE `users`'],
            ['`my``table`', 'SHOW CREATE TABLE `my``table`'],
            ['`my.table`', 'SHOW CREATE TABLE `my.table`'],
            ['app.`order`', 'SHOW CREATE TABLE app.`order`'],
            ['猫', 'SHOW CREATE TABLE 猫'],
            ['order', 'SHOW CREATE TABLE `order`'],
            ['my table', 'SHOW CREATE TABLE `my table`'],
            ['users; DROP TABLE other', 'SHOW CREATE TABLE `users; DROP TABLE other`'],
            ['users WHERE 1', 'SHOW CREATE TABLE `users WHERE 1`'],
            ['', 'SHOW CREATE TABLE ``'],
        ];
    }

    public function testReadableAnswersOnlyStatementsThatNameOneTable(): void
    {
        $statements = new Subject(new MySqlParser());

        self::assertSame('SHOW CREATE TABLE users', $statements->readable('SHOW CREATE TABLE users'));
        self::assertNull($statements->readable('SHOW CREATE TABLE order'));
        self::assertNull($statements->readable('SHOW CREATE TABLE users; DROP TABLE other'));
        self::assertNull($statements->readable('SELECT 1'));
    }

    public function testQuotedWritesOneIdentifierWithItsQuoteDoubled(): void
    {
        self::assertSame('`users`', (new Subject(new MySqlParser()))->quoted('users'));
        self::assertSame('`my``table`', (new Subject(new MySqlParser()))->quoted('my`table'));
        self::assertSame('`app.items`', (new Subject(new MySqlParser()))->quoted('app.items'));
    }
}
