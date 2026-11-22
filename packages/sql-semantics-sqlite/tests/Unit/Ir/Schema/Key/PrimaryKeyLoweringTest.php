<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKeyLowering;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(PrimaryKeyLowering::class)]
#[Small]
final class PrimaryKeyLoweringTest extends TestCase
{
    public function testInlineKeySelectsItsDeclaredColumn(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE logs (message TEXT, id INTEGER PRIMARY KEY DESC ON CONFLICT IGNORE)');
        self::assertInstanceOf(CreateTable::class, $operation->statement);

        $key = (new PrimaryKeyLowering())->inline($operation->statement);

        self::assertNotNull($key);
        self::assertTrue($key->inline);
        self::assertSame(1, $key->terms[0]->slot);
        self::assertTrue($key->terms[0]->descending);
        self::assertSame(KeyConflict::Ignore, $key->conflict);
    }

    public function testLowerKeepsTheTableConstraintOrder(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE logs (message TEXT, id INTEGER, PRIMARY KEY (id DESC, message))');
        self::assertInstanceOf(CreateTable::class, $operation->statement);

        $key = (new PrimaryKeyLowering())->lower($operation->statement);

        self::assertNotNull($key);
        self::assertFalse($key->inline);
        self::assertSame([1, 0], array_column($key->terms, 'slot'));
    }
}
