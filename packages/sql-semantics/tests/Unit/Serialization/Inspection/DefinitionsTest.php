<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Inspection\Definitions;

#[CoversClass(Definitions::class)]
#[Medium]
final class DefinitionsTest extends TestCase
{
    #[TestWith(['SHOW CREATE SCHEMA IF NOT EXISTS app', 'SHOW CREATE DATABASE IF NOT EXISTS `app`'])]
    #[TestWith(['SHOW CREATE EVENT app.daily', 'SHOW CREATE EVENT `app`.`daily`'])]
    #[TestWith(['SHOW CREATE FUNCTION calc', 'SHOW CREATE FUNCTION `calc`'])]
    #[TestWith(['SHOW CREATE PROCEDURE sync', 'SHOW CREATE PROCEDURE `sync`'])]
    #[TestWith(['SHOW CREATE TRIGGER audit', 'SHOW CREATE TRIGGER `audit`'])]
    #[TestWith(['SHOW CREATE TABLE users', 'SHOW CREATE TABLE `users`'])]
    #[TestWith(['SHOW CREATE VIEW users', 'SHOW CREATE VIEW `users`'])]
    #[TestWith(["SHOW CREATE USER 'app'@'localhost'", "SHOW CREATE USER 'app'@'localhost'"])]
    #[TestWith(['SHOW CREATE USER CURRENT_USER()', 'SHOW CREATE USER CURRENT_USER'])]
    #[TestWith(["SHOW FUNCTION STATUS LIKE 'c%'", "SHOW FUNCTION STATUS LIKE 'c%'"])]
    #[TestWith(["SHOW PROCEDURE STATUS WHERE Db = 'app'", "SHOW PROCEDURE STATUS WHERE (`Db` = 'app')"])]
    #[TestWith(['SHOW FUNCTION CODE app.calc', 'SHOW FUNCTION CODE `app`.`calc`'])]
    #[TestWith(['SHOW PROCEDURE CODE sync', 'SHOW PROCEDURE CODE `sync`'])]
    public function testWriteQuotesObjectNamesAndKeepsAccountStrings(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, Definitions::write($statement)?->toString());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
        self::assertNull(Definitions::write($binder->bind('SHOW DATABASES')));
    }

    public function testNamedQuotesEveryPartOfTheName(): void
    {
        self::assertSame('SHOW CREATE EVENT `a``b`.`c d`', Definitions::named('SHOW CREATE EVENT', new QualifiedName(['a`b', 'c d']))->toString());
    }
}
