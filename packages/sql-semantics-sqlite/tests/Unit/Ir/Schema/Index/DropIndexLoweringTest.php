<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexReference;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\MissingIndex;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(DropIndexLowering::class)]
#[Small]
final class DropIndexLoweringTest extends TestCase
{
    public function testLowerResolvesTheTempIndexAndRetainsTheOwner(): void
    {
        $semantics = new Semantics();
        $definitions = $semantics->context([
            'CREATE TABLE main.accounts(code TEXT)',
            'CREATE TABLE temp.scratch(n INTEGER)',
            'CREATE INDEX main.Tag ON accounts(code)',
            'CREATE INDEX temp.tAg ON scratch(n)',
        ]);
        $drop = $semantics->analyze('DROP INDEX tag', $definitions);

        self::assertInstanceOf(DropIndex::class, $drop);
        self::assertInstanceOf(IndexReference::class, $drop->target);
        self::assertSame('temp', $drop->target->schema->value);
        self::assertSame('tAg', $drop->target->name->value);
        self::assertSame('scratch', $drop->target->table->value);
    }

    public function testLowerKeepsTheUnqualifiedAbsenceScope(): void
    {
        $semantics = new Semantics();
        $drop = $semantics->analyze('DROP INDEX IF EXISTS lost', $semantics->context([]));

        self::assertInstanceOf(DropIndex::class, $drop);
        self::assertInstanceOf(MissingIndex::class, $drop->target);
        self::assertNull($drop->target->schema);
        self::assertTrue($drop->ifExists);
        self::assertSame('DROP INDEX IF EXISTS "lost"', $drop->toString());
    }

    public function testLowerPublishesAReservedAbsenceAfterAnExactRowidAlias(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE items (id INTEGER PRIMARY KEY)']);
        $drop = $semantics->analyze('DROP INDEX IF EXISTS main.sqlite_autoindex_items_1', $context);

        self::assertInstanceOf(DropIndex::class, $drop);
        self::assertInstanceOf(MissingIndex::class, $drop->target);
        self::assertSame('DROP INDEX IF EXISTS "main"."sqlite_autoindex_items_1"', $drop->toString());
    }
}
