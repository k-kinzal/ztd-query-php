<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowColumnsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Inspection\Listings;

#[CoversClass(Listings::class)]
#[Medium]
final class ListingsTest extends TestCase
{
    #[TestWith(['SHOW SCHEMAS', 'SHOW DATABASES'])]
    #[TestWith(['SHOW EXTENDED FULL TABLES IN app', 'SHOW EXTENDED FULL TABLES FROM `app`'])]
    #[TestWith(["SHOW FULL FIELDS IN users LIKE 'i%'", "SHOW FULL COLUMNS FROM `users` LIKE 'i%'"])]
    #[TestWith(['SHOW EXTENDED KEYS IN users', 'SHOW EXTENDED INDEX FROM `users`'])]
    #[TestWith(['SHOW TABLE STATUS IN app', 'SHOW TABLE STATUS FROM `app`'])]
    #[TestWith(['SHOW OPEN TABLES', 'SHOW OPEN TABLES'])]
    #[TestWith(["SHOW TRIGGERS IN app LIKE 'a'", "SHOW TRIGGERS FROM `app` LIKE 'a'"])]
    #[TestWith(['SHOW EVENTS', 'SHOW EVENTS'])]
    #[TestWith(['SHOW CHAR SET', 'SHOW CHARACTER SET'])]
    #[TestWith(['SHOW CHARSET WHERE Maxlen > 1', 'SHOW CHARACTER SET WHERE (`Maxlen` > 1)'])]
    #[TestWith(['SHOW COLLATION', 'SHOW COLLATION'])]
    public function testWriteUsesOneCanonicalSpellingPerListing(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, Listings::write($statement)?->toString());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
        self::assertNull(Listings::write($binder->bind('SHOW PROFILES')));
    }

    public function testWriteUsesTheOperandsInsteadOfSourceText(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)'));
        $statement = $binder->bind('SHOW FULL FIELDS FROM users');
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s0', $binder->bind('SELECT 1')->source, Dialect::MySql));
        self::assertSame('SHOW FULL COLUMNS FROM `users`', $copy->toString());
    }

    public function testDetailWritesTheModifiersInGrammarOrder(): void
    {
        self::assertSame('', Listings::detail(false, false));
        self::assertSame('FULL ', Listings::detail(false, true));
        self::assertSame('EXTENDED ', Listings::detail(true, false));
        self::assertSame('EXTENDED FULL ', Listings::detail(true, true));
    }

    public function testListingWritesTheDatabaseSelectorBeforeTheRestriction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW TRIGGERS IN `a b` LIKE 'x'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Inspection\Schema\ShowTriggersStatement::class, $statement);
        self::assertSame("SHOW TRIGGERS FROM `a b` LIKE 'x'", Listings::listing('TRIGGERS', $statement->database, $statement->filter)->toString());
        self::assertSame('SHOW TRIGGERS', Listings::listing('TRIGGERS', null, null)->toString());
    }

    public function testDescribedWritesTheTableWithItsOwnQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE app.users(id INT)')))->bind("SHOW COLUMNS FROM app.users LIKE 'i%'");
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        self::assertSame("SHOW COLUMNS FROM `app`.`users` LIKE 'i%'", Listings::described('COLUMNS', $statement->table, $statement->filter)->toString());
    }
}
