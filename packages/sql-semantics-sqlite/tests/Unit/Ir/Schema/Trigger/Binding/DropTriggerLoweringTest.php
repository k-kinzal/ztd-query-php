<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\DropTriggerLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\DropTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\MissingTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerReference;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerVerification;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(DropTriggerLowering::class)]
#[Small]
final class DropTriggerLoweringTest extends TestCase
{
    public function testLowerChoosesATemporaryNameBeforeItsPersistentSibling(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context([
            'CREATE TABLE watched (id)',
            'CREATE TRIGGER main.Audit AFTER INSERT ON main.watched BEGIN SELECT NEW.id; END',
            'CREATE TEMP TRIGGER Audit AFTER INSERT ON main.watched BEGIN SELECT NEW.id; END',
        ]);
        $drop = $dialect->analyze('DROP TRIGGER AUDIT', $context);

        self::assertInstanceOf(DropTrigger::class, $drop);
        self::assertInstanceOf(TriggerReference::class, $drop->target);
        self::assertSame('temp', $drop->target->schema->value);
        self::assertSame('main', $drop->target->watchedSchema->value);
        self::assertSame('DROP TRIGGER "temp"."Audit"', $drop->toString());
    }

    public function testLowerDistinguishesAnUnattachedQualifierFromTheWholeSearchPath(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context([]);
        $drop = $dialect->analyze('DROP TRIGGER IF EXISTS skipped.audit', $context);

        self::assertInstanceOf(DropTrigger::class, $drop);
        self::assertInstanceOf(MissingTrigger::class, $drop->target);
        self::assertSame(TriggerVerification::UnknownSchema, $drop->target->verification);
        self::assertSame('DROP TRIGGER IF EXISTS "skipped"."audit"', $drop->toString());
    }
}
