<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\ForeignKeyAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\ForeignKeyAuditLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(ForeignKeyAuditLowering::class)]
#[Small]
final class ForeignKeyAuditLoweringTest extends TestCase
{
    public function testLowerSelectsMainForNoArgumentAndTempForTheShadowedChild(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context([
            'CREATE TABLE main.child(id INT REFERENCES parent(id))',
            'CREATE TEMP TABLE child(id INT REFERENCES parent(id))',
        ]);
        $schema = $semantics->analyze('PRAGMA foreign_key_check', $catalog);
        $child = $semantics->analyze('PRAGMA foreign_key_check(child)', $catalog);

        self::assertInstanceOf(ForeignKeyAudit::class, $schema);
        self::assertInstanceOf(Identifier::class, $schema->target);
        self::assertSame('main', $schema->target->value);
        self::assertInstanceOf(ForeignKeyAudit::class, $child);
        self::assertInstanceOf(RelationTarget::class, $child->target);
        self::assertSame('temp', $child->target->schema->value);
        self::assertSame('PRAGMA "temp".foreign_key_check("child")', $child->toString());
    }
}
