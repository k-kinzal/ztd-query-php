<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Locale;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Collation\CollationProvider;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Locale\CreateCollationStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateCollationStatement::class)]
#[Medium]
final class CreateCollationStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE COLLATION s.c (lc_collate = 'de_DE.utf8', lc_ctype = 'de_DE.utf8', provider = LIBC, version = '2.36')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame(CollationProvider::Libc, $statement->provider);
        self::assertNull($statement->locale);
        self::assertSame('de_DE.utf8', $statement->lcCtype);
        self::assertSame('2.36', $statement->version);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame("CREATE COLLATION \"s\".\"c\"(LC_COLLATE = 'de_DE.utf8', LC_CTYPE = 'de_DE.utf8', VERSION = '2.36')", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith([CollationProvider::Libc, 'C', 'C', null])]
    #[TestWith([CollationProvider::Libc, null, 'C', null])]
    #[TestWith([CollationProvider::Icu, null, 'en', 'en'])]
    #[TestWith([CollationProvider::Builtin, 'c.utf8', null, null])]
    public function testSettingsRejectsIncompleteOrConflictingLocales(CollationProvider $provider, ?string $locale, ?string $lcCollate, ?string $lcCtype): void
    {
        $this->expectException(InvalidStructure::class);
        CreateCollationStatement::settings($provider, $locale, $lcCollate, $lcCtype);
    }

    public function testRejectsANondeterministicLibcCollation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (locale = 'C')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDeterministic(false);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (locale = 'C')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame("CREATE COLLATION \"c\"(LOCALE = 'C')", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (locale = 'C')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame(['s', 'd'], $statement->withName(new QualifiedName(['s', 'd']))->name->parts);
        self::assertSame(['c'], $statement->name->parts);
    }

    public function testWithProviderReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (locale = 'C')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame("CREATE COLLATION \"c\"(PROVIDER = 'builtin', LOCALE = 'C')", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withProvider(CollationProvider::Builtin)));
    }

    public function testWithLocaleReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (locale = 'C')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame('POSIX', $statement->withLocale('POSIX')->locale);
    }

    public function testWithCategoriesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (lc_collate = 'C', lc_ctype = 'C')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame("CREATE COLLATION \"c\"(LC_COLLATE = 'POSIX', LC_CTYPE = 'C')", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withCategories('POSIX', 'C')));
    }

    public function testWithDeterministicReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (provider = icu, locale = 'und')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertFalse($statement->withDeterministic(false)->deterministic);
        self::assertTrue($statement->deterministic);
    }

    public function testWithRulesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (provider = icu, locale = 'und')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame("CREATE COLLATION \"c\"(PROVIDER = 'icu', LOCALE = 'und', RULES = '&a < b')", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withRules('&a < b')));
    }

    public function testWithVersionReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (locale = 'C')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame('1', $statement->withVersion('1')->version);
    }

    public function testWithIfNotExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (locale = 'C')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame("CREATE COLLATION IF NOT EXISTS \"c\"(LOCALE = 'C')", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withIfNotExists(true)));
    }

    public function testDefaultsToADeterministicUnconditionalCreation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (locale = 'C')");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        $rebuilt = new CreateCollationStatement($statement->origin, new QualifiedName(['c']), CollationProvider::Libc, 'C');
        self::assertTrue($rebuilt->deterministic);
        self::assertFalse($rebuilt->ifNotExists);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($rebuilt));
    }

    public function testRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new CreateCollationStatement($statement->origin, new QualifiedName(['c']), CollationProvider::Libc, 'C');
    }

    public function testRejectsAnOverlongName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new CreateCollationStatement($statement->origin, new QualifiedName(['a', 'b', 'c', 'd']), CollationProvider::Libc, 'C');
    }

    public function testRejectsAMissingLocale(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new CreateCollationStatement($statement->origin, new QualifiedName(['c']), CollationProvider::Libc, null);
    }
}
