<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexReference;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\MissingIndex;
use SqlSemantics\Platform\PostgreSql\Model\Contract\SearchPath;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(DropIndexLowering::class)]
#[Medium]
final class DropIndexLoweringTest extends TestCase
{
    public function testLowerEarlierIndexWinsOverWrongKindInLaterSchema(): void
    {
        $analyzer = new Semantics(searchPath: new SearchPath('front', 'later', 'public'));
        $context = $analyzer->context([
            'CREATE SCHEMA front CREATE TABLE accounts (id integer) CREATE INDEX selected ON accounts(id)',
            'CREATE TABLE later.selected (unrelated integer)',
        ]);
        $drop = $analyzer->analyze('DROP INDEX selected, front.selected', $context);

        self::assertInstanceOf(DropIndex::class, $drop);
        self::assertInstanceOf(IndexReference::class, $drop->targets[0]);
        self::assertSame('accounts', $drop->targets[0]->table->value);
        self::assertSame('DROP INDEX "front"."selected", "front"."selected" RESTRICT', $drop->toString());
    }

    public function testAbsenceDistinguishesAPathEntryFromAKnownEmptySchema(): void
    {
        $analyzer = new Semantics(searchPath: new SearchPath('unknown', 'empty'));
        $context = $analyzer->context(['CREATE SCHEMA empty']);
        $drop = $analyzer->analyze('DROP INDEX IF EXISTS unknown.idx, empty.idx, idx', $context);

        self::assertInstanceOf(DropIndex::class, $drop);
        self::assertCount(3, $drop->targets);
        self::assertInstanceOf(MissingIndex::class, $drop->targets[0]);
        self::assertInstanceOf(MissingIndex::class, $drop->targets[1]);
        self::assertInstanceOf(MissingIndex::class, $drop->targets[2]);
        self::assertTrue($drop->targets[0]->missingSchema);
        self::assertFalse($drop->targets[1]->missingSchema);
        self::assertNull($drop->targets[2]->schema);
    }

    public function testAFormerlySkippedPathEntryDoesNotMoveAnInstalledIndex(): void
    {
        $analyzer = new Semantics(searchPath: new SearchPath('later', 'public'));
        $context = $analyzer->context([
            'CREATE TABLE accounts (id integer)',
            'CREATE SCHEMA later',
            'CREATE INDEX idx ON accounts(id)',
        ]);
        $drop = $analyzer->analyze('DROP INDEX idx', $context);

        self::assertInstanceOf(DropIndex::class, $drop);
        self::assertInstanceOf(IndexReference::class, $drop->targets[0]);
        self::assertSame('public', $drop->targets[0]->schema->value);
    }
}
