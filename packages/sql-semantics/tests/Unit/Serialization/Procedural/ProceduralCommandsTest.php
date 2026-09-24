<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\ProceduralCommands;

#[CoversClass(ProceduralCommands::class)]
#[Medium]
final class ProceduralCommandsTest extends TestCase
{
    public function testWriteRoutesProceduralStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertSame('LOCK INSTANCE FOR BACKUP', ProceduralCommands::write($binder->bind('LOCK INSTANCE FOR BACKUP'))?->toString());
        self::assertNull(ProceduralCommands::write($binder->bind('SELECT 1')));
    }
}
