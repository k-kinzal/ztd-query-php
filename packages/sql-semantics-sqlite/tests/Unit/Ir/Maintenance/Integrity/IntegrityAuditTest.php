<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Integrity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityLimit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;

#[CoversClass(IntegrityAudit::class)]
#[Small]
final class IntegrityAuditTest extends TestCase
{
    public function testToStringKeepsTheModeScopeAndTargetRoles(): void
    {
        $schema = new Identifier('temp');
        $table = new RelationTarget($schema, new Identifier('7'));

        self::assertSame('PRAGMA integrity_check', (new IntegrityAudit(IntegrityKind::Full))->toString());
        self::assertSame('PRAGMA quick_check("7")', (new IntegrityAudit(IntegrityKind::Quick, $table))->toString());
        self::assertSame('PRAGMA "temp".quick_check(7)', (new IntegrityAudit(IntegrityKind::Quick, new IntegrityLimit(7), $schema))->toString());
        self::assertSame('PRAGMA "temp".quick_check("sqlite_temp_master")', (new IntegrityAudit(IntegrityKind::Quick, new CatalogTable($schema), $schema))->toString());
    }

    public function testProfileIdIdentifiesThePinnedProfile(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new IntegrityAudit(IntegrityKind::Full))->profileId());
    }
}
