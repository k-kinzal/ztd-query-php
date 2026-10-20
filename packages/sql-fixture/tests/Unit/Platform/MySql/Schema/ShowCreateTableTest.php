<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\ShowCreateTable as Subject;

#[CoversClass(Subject::class)]
final class ShowCreateTableTest extends TestCase
{
    #[DataProvider('providerTableNames')]
    public function testStatementKeepsOneTableReferenceAndQuotesAnyOtherName(string $tableName, string $expected): void
    {
        self::assertSame($expected, (new Subject())->statement($tableName));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerTableNames(): array
    {
        return [
            ['users', 'SHOW CREATE TABLE users'],
            ['app.items', 'SHOW CREATE TABLE app.items'],
            ['`users`', 'SHOW CREATE TABLE users'],
            ['`my``table`', 'SHOW CREATE TABLE `my``table`'],
            ['`my.table`', 'SHOW CREATE TABLE `my.table`'],
            ['app.`order`', 'SHOW CREATE TABLE app.`order`'],
            ['猫', 'SHOW CREATE TABLE `猫`'],
            ['order', 'SHOW CREATE TABLE `order`'],
            ['my table', 'SHOW CREATE TABLE `my table`'],
            ['my`table', 'SHOW CREATE TABLE `my``table`'],
            ['users; DROP TABLE other', 'SHOW CREATE TABLE `users; DROP TABLE other`'],
            ['users WHERE 1', 'SHOW CREATE TABLE `users WHERE 1`'],
            ['', 'SHOW CREATE TABLE ``'],
        ];
    }

    public function testStatementReadsTheNameWithTheGrammarOfTheRelease(): void
    {
        self::assertSame('SHOW CREATE TABLE `rank`', (new Subject('mysql-8.4.7'))->statement('rank'));
        self::assertSame('SHOW CREATE TABLE rank', (new Subject('mysql-5.7.44'))->statement('rank'));
    }

    public function testWrittenAnswersOnlyTextThatNamesOneTable(): void
    {
        self::assertNotNull((new Subject())->written('users'));
        self::assertNull((new Subject())->written('order'));
        self::assertNull((new Subject())->written('users; DROP TABLE other'));
        self::assertNull((new Subject())->written('users LIKE x'));
    }

    public function testBuiltTakesTheWholeTextAsOneName(): void
    {
        self::assertSame('SHOW CREATE TABLE `app.items`', (new Subject())->built('app.items')->toString());
        self::assertSame('SHOW CREATE TABLE users', (new Subject())->built('users')->toString());
    }
}
