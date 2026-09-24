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
        self::assertSame("CREATE COLLATION \"s\".\"c\"(LC_COLLATE = 'de_DE.utf8', LC_CTYPE = 'de_DE.utf8', VERSION = '2.36')", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
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
        self::assertSame("CREATE COLLATION \"c\"(LOCALE = 'C')", $statement->withOrigin($statement->origin)->toString());
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
        self::assertSame("CREATE COLLATION \"c\"(PROVIDER = 'builtin', LOCALE = 'C')", $statement->withProvider(CollationProvider::Builtin)->toString());
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
        self::assertSame("CREATE COLLATION \"c\"(LC_COLLATE = 'POSIX', LC_CTYPE = 'C')", $statement->withCategories('POSIX', 'C')->toString());
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
        self::assertSame("CREATE COLLATION \"c\"(PROVIDER = 'icu', LOCALE = 'und', RULES = '&a < b')", $statement->withRules('&a < b')->toString());
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
        self::assertSame("CREATE COLLATION IF NOT EXISTS \"c\"(LOCALE = 'C')", $statement->withIfNotExists(true)->toString());
    }
}
