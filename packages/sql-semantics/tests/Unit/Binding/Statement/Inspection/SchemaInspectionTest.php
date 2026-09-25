<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\SchemaInspection;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SchemaInspection::class)]
#[Medium]
final class SchemaInspectionTest extends TestCase
{
    #[DataProvider('providerForms')]
    public function testBindClassifiesEveryFormOfTheFamilyOnEveryRelease(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE users(id INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(InspectionStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $copy = $binder->bind($expected);
        self::assertInstanceOf(InspectionStatement::class, $copy);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($copy));
        self::assertSame(array_column($statement->resultColumns(), 'name'), array_column($copy->resultColumns(), 'name'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerForms(): iterable
    {
        $forms = [
            ['SHOW SCHEMAS', 'SHOW DATABASES'],
            ["SHOW FULL TABLES IN app LIKE 'u%'", "SHOW FULL TABLES FROM `app` LIKE 'u%'"],
            ['SHOW FULL FIELDS IN users WHERE Field IS NOT NULL', 'SHOW FULL COLUMNS FROM `users` WHERE (`Field` IS NOT NULL)'],
            ['SHOW KEYS IN users WHERE Non_unique = 0', 'SHOW INDEX FROM `users` WHERE (`Non_unique` = 0)'],
            ["SHOW TABLE STATUS IN app LIKE 'u%'", "SHOW TABLE STATUS FROM `app` LIKE 'u%'"],
            ['SHOW OPEN TABLES FROM app', 'SHOW OPEN TABLES FROM `app`'],
            ["SHOW TRIGGERS LIKE 'u%'", "SHOW TRIGGERS LIKE 'u%'"],
            ['SHOW EVENTS IN app', 'SHOW EVENTS FROM `app`'],
            ['SHOW CHAR SET', 'SHOW CHARACTER SET'],
            ["SHOW COLLATION LIKE 'utf8%'", "SHOW COLLATION LIKE 'utf8%'"],
            ['SHOW CREATE SCHEMA app', 'SHOW CREATE DATABASE `app`'],
            ['SHOW CREATE EVENT app.daily', 'SHOW CREATE EVENT `app`.`daily`'],
            ['SHOW CREATE FUNCTION calc', 'SHOW CREATE FUNCTION `calc`'],
            ['SHOW CREATE PROCEDURE sync', 'SHOW CREATE PROCEDURE `sync`'],
            ['SHOW CREATE TABLE users', 'SHOW CREATE TABLE `users`'],
            ['SHOW CREATE TRIGGER app.audit', 'SHOW CREATE TRIGGER `app`.`audit`'],
            ['SHOW CREATE VIEW users', 'SHOW CREATE VIEW `users`'],
            ['SHOW FUNCTION STATUS', 'SHOW FUNCTION STATUS'],
            ["SHOW PROCEDURE STATUS LIKE 's%'", "SHOW PROCEDURE STATUS LIKE 's%'"],
            ['SHOW FUNCTION CODE app.calc', 'SHOW FUNCTION CODE `app`.`calc`'],
            ['SHOW PROCEDURE CODE sync', 'SHOW PROCEDURE CODE `sync`'],
            ['SHOW ENGINE INNODB STATUS', 'SHOW ENGINE `INNODB` STATUS'],
            ['SHOW ENGINE ALL MUTEX', 'SHOW ENGINE ALL MUTEX'],
            ["SHOW ENGINE 'ndb' LOGS", 'SHOW ENGINE `ndb` LOGS'],
            ['SHOW PROFILE CPU, BLOCK IO FOR QUERY 3 LIMIT 2, 5', 'SHOW PROFILE CPU, BLOCK IO FOR QUERY 3 LIMIT 5 OFFSET 2'],
            ['SHOW PROFILES', 'SHOW PROFILES'],
        ];
        foreach (['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.4.7', 'mysql-9.1.0'] as $version) {
            foreach ($forms as [$sql, $expected]) {
                yield $version . ': ' . $sql => [$version, $sql, $expected];
            }
        }
        yield 'mysql-5.7.44: SHOW CREATE USER' => ['mysql-5.7.44', "SHOW CREATE USER 'app'@'localhost'", "SHOW CREATE USER 'app'@'localhost'"];
        yield 'mysql-8.4.7: SHOW CREATE USER' => ['mysql-8.4.7', 'SHOW CREATE USER CURRENT_USER()', 'SHOW CREATE USER CURRENT_USER'];
        yield 'mysql-8.4.7: SHOW EXTENDED' => ['mysql-8.4.7', 'SHOW EXTENDED FULL COLUMNS FROM users', 'SHOW EXTENDED FULL COLUMNS FROM `users`'];
        yield 'mysql-8.4.7: SHOW PARSE_TREE' => ['mysql-8.4.7', 'SHOW PARSE_TREE SHOW CHARSET', 'SHOW PARSE_TREE SHOW CHARACTER SET'];
    }

    #[TestWith(['SHOW ENGINES'])]
    #[TestWith(['SHOW PROCESSLIST'])]
    #[TestWith(['SELECT 1'])]
    public function testBindLeavesOtherStatementsToTheirOwnFamilies(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertNotInstanceOf(InspectionStatement::class, $statement);
    }

    public function testBindIgnoresOtherDialects(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertNotInstanceOf(InspectionStatement::class, $statement);
    }
}
