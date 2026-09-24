<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\ServerCommands;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsToStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ServerCommands::class)]
#[Medium]
final class ServerCommandsTest extends TestCase
{
    public function testBindRoutesAServerCommandToItsBinder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'a'");
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $statement);
    }

    public function testBindLeavesOtherStatementsToTheirFamilies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DO 1');
        self::assertSame('DO', $statement->kind->value);
    }
}
