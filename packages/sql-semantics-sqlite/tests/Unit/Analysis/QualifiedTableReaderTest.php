<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\QualifiedTableReader;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(QualifiedTableReader::class)]
#[Medium]
final class QualifiedTableReaderTest extends TestCase
{
    #[TestWith(['DELETE FROM main.bar AS b', 'main', 'bar', 'b'])]
    #[TestWith(['UPDATE bar SET foo = 1', null, 'bar', null])]
    #[TestWith(['INSERT INTO "main"."bar" AS "b" VALUES(1)', 'main', 'bar', 'b'])]
    public function testReadKeepsNamespaceAndAliasRoles(string $sql, ?string $schema, string $name, ?string $alias): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $source = (new SqliteParser())->parse($sql)->find('xfullname')[0];
        $target = (new QualifiedTableReader())->read($source, $catalog);
        self::assertSame($schema, $target->name->schema?->value);
        self::assertSame($name, $target->name->name->value);
        self::assertSame($alias, $target->alias?->value);
        self::assertSame($catalog, $target->catalog);
    }
}
