<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(CreateTrigger::class)]
#[Small]
final class CreateTriggerTest extends TestCase
{
    public function testToStringUsesTheResolvedWatchedImageAndOrderedBody(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (id)', 'CREATE TABLE audit (id)']);
        $result = $dialect->analyze('CREATE TRIGGER tr AFTER INSERT ON t BEGIN INSERT INTO audit VALUES (new.id); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertSame('CREATE TRIGGER "main"."tr" AFTER INSERT ON "main"."t" BEGIN INSERT INTO "audit" ("id") VALUES (NEW."id"); END', $result->toString());
    }

    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (id)', 'CREATE TABLE audit (id)']);
        $result = $dialect->analyze('CREATE TRIGGER tr AFTER DELETE ON t BEGIN INSERT INTO audit VALUES (old.id); END', $context);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $result->profileId());
    }
}
