<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Locale\CreateCollationStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\TypeSystem\Locales;

#[CoversClass(Locales::class)]
#[Medium]
final class LocalesTest extends TestCase
{
    #[TestWith(["CREATE CONVERSION c FOR 'utf8' TO 'latin1' FROM f", "CREATE CONVERSION \"c\" FOR 'UTF8' TO 'LATIN1' FROM \"f\""])]
    #[TestWith(['CREATE COLLATION c FROM d', 'CREATE COLLATION "c" FROM "d"'])]
    #[TestWith(["CREATE COLLATION c (provider = icu, locale = 'und', deterministic = false, rules = 'r', version = 'v')", "CREATE COLLATION \"c\"(PROVIDER = 'icu', LOCALE = 'und', DETERMINISTIC = FALSE, RULES = 'r', VERSION = 'v')"])]
    #[TestWith(['ALTER COLLATION c REFRESH VERSION', 'ALTER COLLATION "c" REFRESH VERSION'])]
    public function testWriteSpellsEachForm(string $sql, string $expected): void
    {
        self::assertSame($expected, Locales::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    public function testSettingsOmitsDefaults(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE COLLATION c (provider = libc, locale = 'C', deterministic = true)");
        self::assertInstanceOf(CreateCollationStatement::class, $statement);
        self::assertSame(["LOCALE = 'C'"], array_map(static fn ($setting): string => $setting->toString(), Locales::settings($statement)));
    }

    public function testWriteIgnoresOtherStatements(): void
    {
        self::assertNull(Locales::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }
}
