<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\Locales;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\TypeSystem\Collation\CollationProvider;
use SqlSemantics\Model\Definition\TypeSystem\Conversion\ServerEncoding;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Locale as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Locales::class)]
#[Medium]
final class LocalesTest extends TestCase
{
    public function testConversionReadsTheEncodingsAndFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE DEFAULT CONVERSION c FOR 'ShiftJIS' TO 'utf8' FROM s.f");
        self::assertInstanceOf(Statement\CreateConversionStatement::class, $statement);
        self::assertSame(ServerEncoding::Sjis, $statement->sourceEncoding);
        self::assertSame(['s', 'f'], $statement->function->parts);
        self::assertTrue($statement->isDefault);
    }

    #[TestWith(["CREATE CONVERSION c FOR 'text' TO 'utf8' FROM f"])]
    #[TestWith(["CREATE CONVERSION c FOR 'utf8' TO 'sql-ascii' FROM f"])]
    public function testConversionRejectsAnUnsupportedEncoding(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ConversionEncoding->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    #[TestWith(['UTF-8', ServerEncoding::Utf8])]
    #[TestWith(['iso_8859_15', ServerEncoding::Latin9])]
    #[TestWith(['Windows-932', ServerEncoding::Sjis])]
    #[TestWith(['cp1251', null])]
    public function testEncodingIgnoresCaseAndPunctuation(string $name, ?ServerEncoding $expected): void
    {
        self::assertSame($expected, Locales::encoding($name));
    }

    public function testCollationReadsSettingsAndCopies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $created = $binder->bind("CREATE COLLATION IF NOT EXISTS c (provider = 'ICU', locale = 'und', deterministic = off)");
        self::assertInstanceOf(Statement\CreateCollationStatement::class, $created);
        self::assertSame(CollationProvider::Icu, $created->provider);
        self::assertFalse($created->deterministic);
        self::assertTrue($created->ifNotExists);
        $copied = $binder->bind('CREATE COLLATION IF NOT EXISTS c FROM s.d');
        self::assertInstanceOf(Statement\CopyCollationStatement::class, $copied);
        self::assertSame(['s', 'd'], $copied->copied->parts);
    }

    #[TestWith(["CREATE COLLATION c (locale = 'C', locale = 'C')", 'definition-attribute'])]
    #[TestWith(["CREATE COLLATION c (\"LOCALE\" = 'C')", 'definition-attribute'])]
    #[TestWith(["CREATE COLLATION c (from = d, locale = 'C')", 'definition-requirement'])]
    #[TestWith(["CREATE COLLATION c (lc_collate = 'C')", 'definition-requirement'])]
    #[TestWith(["CREATE COLLATION c (provider = other, locale = 'C')", 'definition-argument'])]
    #[TestWith(['CREATE COLLATION c (locale)', 'definition-argument'])]
    public function testCollationDiagnosesImpossibleSettings(string $sql, string $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::from($violation)->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testCollationRejectsAnOverQualifiedCopy(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE COLLATION c (from = a.b.c)');
    }

    public function testRefreshReadsTheCollation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER COLLATION s.c REFRESH VERSION');
        self::assertInstanceOf(Statement\RefreshCollationVersionStatement::class, $statement);
        self::assertSame(['s', 'c'], $statement->collation->parts);
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindReadsQualifiedLocaleObjects(): array
    {
        return [
            [Dialect::PostgreSql, null, 'CREATE CONVERSION s.c FOR \'UTF8\' TO \'LATIN1\' FROM s.f', [Statement\CreateConversionStatement::class, 'CREATE CONVERSION "s"."c" FOR \'UTF8\' TO \'LATIN1\' FROM "s"."f"']],
            [Dialect::PostgreSql, null, 'CREATE COLLATION s.c (locale = \'C\')', [Statement\CreateCollationStatement::class, 'CREATE COLLATION "s"."c"(LOCALE = \'C\')']],
            [Dialect::PostgreSql, null, 'CREATE COLLATION s.c FROM s.d', [Statement\CopyCollationStatement::class, 'CREATE COLLATION "s"."c" FROM "s"."d"']],
            [Dialect::PostgreSql, null, 'ALTER COLLATION s.c REFRESH VERSION', [Statement\RefreshCollationVersionStatement::class, 'ALTER COLLATION "s"."c" REFRESH VERSION']],
            [Dialect::PostgreSql, null, 'CREATE COLLATION c (provider = icu, locale = \'und\', deterministic = false)', [Statement\CreateCollationStatement::class, 'CREATE COLLATION "c"(PROVIDER = \'icu\', LOCALE = \'und\', DETERMINISTIC = FALSE)']],
        ];
    }

    #[DataProvider('providerBindReadsQualifiedLocaleObjects')]
    public function testBindReadsQualifiedLocaleObjects(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, $statement->toString()]);
    }

    #[TestWith(["CREATE CONVERSION a.s.c FOR 'UTF8' TO 'LATIN1' FROM f"])]
    #[TestWith(["CREATE CONVERSION c FOR 'UTF8' TO 'LATIN1' FROM a.s.f"])]
    #[TestWith(["CREATE CONVERSION c FOR 'SQL_ASCII' TO 'UTF8' FROM f"])]
    #[TestWith(["CREATE COLLATION a.s.c (locale = 'C')"])]
    #[TestWith(['CREATE COLLATION c FROM a.s.d'])]
    #[TestWith(['ALTER COLLATION a.s.c REFRESH VERSION'])]
    public function testBindRejectsAnImpossibleLocaleObject(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
