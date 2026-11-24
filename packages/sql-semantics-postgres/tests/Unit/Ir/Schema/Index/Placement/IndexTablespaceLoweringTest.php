<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Placement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespaceLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(IndexTablespaceLowering::class)]
final class IndexTablespaceLoweringTest extends TestCase
{
    public function testResolvesTheEffectiveDeclaredPlacement(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE SCHEMA app',
            'CREATE TABLE app.events (id integer)',
            'CREATE INDEX event_key ON app.events (id)',
            'CREATE TABLESPACE "cold archive" LOCATION \'/sql-context/cold\'',
        ]);
        $value = $semantics->analyze('REINDEX (TABLESPACE earlier, VERBOSE, TABLESPACE "cold archive") INDEX app.event_key', $context);

        self::assertInstanceOf(ReindexIndex::class, $value);
        self::assertSame('cold archive', $value->tablespace?->name->value);
        self::assertSame('REINDEX (VERBOSE, TABLESPACE "cold archive") INDEX "app"."event_key"', $value->toString());
    }

    public function testRejectsAnUnresolvedEffectivePlacement(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE SCHEMA app',
            'CREATE TABLE app.events (id integer)',
            'CREATE INDEX event_key ON app.events (id)',
        ]);

        try {
            $semantics->analyze('REINDEX (TABLESPACE pg_default, TABLESPACE missing_archive) INDEX app.event_key', $context);
        } catch (InvalidConstruction $failure) {
            self::assertStringContainsString('requires a declared tablespace', $failure->getMessage());

            return;
        }

        self::fail('An unresolved placement was published.');
    }
}
