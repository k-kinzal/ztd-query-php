<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\AlterIndexRenameLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\RenameIndexes;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlterIndexRenameLowering::class)]
#[Medium]
final class AlterIndexRenameLoweringTest extends TestCase
{
    public function testMovesKeepsOriginalOrderAroundOptions(): void
    {
        $parsed = (new Inspection())->inspect('ALTER TABLE t LOCK=NONE, RENAME INDEX a TO b, RENAME KEY b TO c')->statement;
        self::assertInstanceOf(AlterTable::class, $parsed);
        $moves = (new AlterIndexRenameLowering())->moves($parsed);
        self::assertNotNull($moves);

        self::assertSame('a', $moves[0]->from->column->value);
        self::assertSame('c', $moves[1]->to->column->value);
    }

    public function testLowerUsesTheOwnerNamespaceToPublishTheSelectedMove(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY original(a), KEY untouched(b))']);
        $renamed = $semantics->analyze('ALTER TABLE app.t RENAME INDEX original TO changed, LOCK=SHARED, LOCK=NONE', $context);
        self::assertInstanceOf(RenameIndexes::class, $renamed);

        self::assertSame('original', $renamed->moves[0]->source->value);
        self::assertSame('changed', $renamed->moves[0]->destination->value);
        self::assertSame('NONE', $renamed->policy->lock->value);
    }

    public function testUnmovedFindsNothingWhenAllKeysMove(): void
    {
        $keys = [];
        $analyzed = new AlterIndexRenameLowering();

        self::assertSame([], $analyzed->unmoved($keys, [
            new \SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename(
                new \SqlSemantics\Platform\MySql\Ir\Identifier('a'),
                new \SqlSemantics\Platform\MySql\Ir\Identifier('b'),
            ),
        ]));
    }

    public function testQualifierAcceptsAnAbsentQualifier(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE app.t (a INT, KEY original(a))']);
        $renamed = $semantics->analyze('ALTER TABLE app.t RENAME INDEX original TO changed', $context);
        self::assertInstanceOf(RenameIndexes::class, $renamed);
        (new AlterIndexRenameLowering())->qualifier($renamed->owner, null);

        self::assertSame('t', $renamed->owner->name->table->value);
    }
}
