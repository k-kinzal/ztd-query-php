<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Server\ServerCommands;

#[CoversClass(ServerCommands::class)]
#[Medium]
final class ServerCommandsTest extends TestCase
{
    public function testWriteRoutesOnlyServerCommands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertNotNull(ServerCommands::write($binder->bind("PURGE BINARY LOGS TO 'a'")));
        self::assertNull(ServerCommands::write($binder->bind('DO 1')));
    }
}
