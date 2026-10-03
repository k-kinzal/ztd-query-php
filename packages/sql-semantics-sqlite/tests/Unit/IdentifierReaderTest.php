<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(IdentifierReader::class)]
#[Medium]
final class IdentifierReaderTest extends TestCase
{
    public function testNameDecodesAQuotedIdentifierWithoutRetainingItsToken(): void
    {
        $name = (new SqliteParser())->parse('DROP TABLE "a""b"')->find('nm')[0];
        $decoded = (new IdentifierReader())->name($name);
        self::assertSame('a"b', $decoded->value);
        self::assertSame(Quote::Double, $decoded->quote);
        self::assertTrue((new SemanticGraph())->containsOnlyValues($decoded));
    }

    public function testQualifiedDistinguishesASchemaFromAnObjectName(): void
    {
        $source = (new SqliteParser())->parse('DROP TABLE main."a.b"')->find('fullname')[0];
        $decoded = (new IdentifierReader())->qualified($source);
        self::assertSame('a.b', $decoded->name->value);
        self::assertSame('main', $decoded->schema?->value);
    }

    public function testDirectNamesDoesNotIncludeTheTableOrItsSchema(): void
    {
        $command = (new SqliteParser())->parse('ALTER TABLE main.users RENAME COLUMN before TO after')->find('cmd')[0];
        $names = (new IdentifierReader())->directNames($command);
        self::assertSame(['before', 'after'], array_column($names, 'value'));
    }
}
