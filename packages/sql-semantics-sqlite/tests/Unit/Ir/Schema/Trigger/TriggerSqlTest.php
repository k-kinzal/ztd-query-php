<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TriggerSql::class)]
#[Small]
final class TriggerSqlTest extends TestCase
{
    public function testStatementPreservesTheExplicitTemporaryKeyword(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (id)', 'CREATE TABLE audit (id)']);
        $result = $dialect->analyze('CREATE TEMP TRIGGER tr BEFORE INSERT ON main.t BEGIN INSERT INTO audit VALUES (NEW.id); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertStringStartsWith('CREATE TEMP TRIGGER "tr" BEFORE INSERT ON "main"."t"', (new TriggerSql())->statement($result));
    }

    public function testInsertionUsesAnUnqualifiedBodyTarget(): void
    {
        $dialect = new Semantics();
        $context = $dialect->context(['CREATE TABLE t (id)', 'CREATE TABLE audit (a,b)']);
        $result = $dialect->analyze('CREATE TRIGGER tr UPDATE ON t BEGIN INSERT OR REPLACE INTO audit (b,a) VALUES (OLD.id,NEW.id); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertSame(
            'INSERT OR REPLACE INTO "audit" ("b", "a") VALUES (OLD."id", NEW."id")',
            (new TriggerSql())->insertion($result->program[0], $result->watched)
        );
    }

    public function testFieldQuotesTheDeclaredColumnName(): void
    {
        $source = new TableSource(new Identifier('main'), new Identifier('t'), [new Identifier('needs"quote')]);
        self::assertSame('NEW."needs""quote"', (new TriggerSql())->field(new TriggerField(RowImage::New, 0), $source));
    }
}
