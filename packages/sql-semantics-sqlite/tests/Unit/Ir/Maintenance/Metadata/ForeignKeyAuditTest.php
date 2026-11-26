<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\ForeignKeyAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;

#[CoversClass(ForeignKeyAudit::class)]
#[Small]
final class ForeignKeyAuditTest extends TestCase
{
    public function testToStringWritesAnAbsoluteAuditTarget(): void
    {
        $schema = new Identifier('ext "arc"');
        $target = new RelationTarget($schema, new Identifier('pending accounts'));

        self::assertSame('PRAGMA "main".foreign_key_check', (new ForeignKeyAudit())->toString());
        self::assertSame('PRAGMA "ext ""arc""".foreign_key_check("pending accounts")', (new ForeignKeyAudit($target))->toString());
    }

    public function testToStringDistinguishesBuiltinCatalogAccess(): void
    {
        $schema = new Identifier('temp');
        $target = new CatalogTable($schema);

        self::assertSame('PRAGMA foreign_key_check("sqlite_temp_master")', (new ForeignKeyAudit($target))->toString());
        self::assertSame('PRAGMA "temp".foreign_key_check("sqlite_temp_master")', (new ForeignKeyAudit($target, $schema))->toString());
    }

    public function testProfileIdIdentifiesTheNativeLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new ForeignKeyAudit())->profileId());
    }
}
