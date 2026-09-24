<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Source\IgnoredServers;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IgnoredServers::class)]
#[Medium]
final class IgnoredServersTest extends TestCase
{
    public function testOptionIsIgnoreServerIds(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO IGNORE_SERVER_IDS = (1), IGNORE_SERVER_IDS = (2)');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(IgnoredServers::class, $statement->settings[0]);
        self::assertSame(SourceOption::IgnoreServerIds, $statement->settings[0]->option());
        self::assertSame(['1', '2'], array_map(static fn ($id): string => $id->text, $statement->settings[0]->servers));
    }

    public function testRejectsATextServerId(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Replication\Source\SourceText::class, $statement->settings[0]);
        $this->expectException(InvalidStructure::class);
        new IgnoredServers([$statement->settings[0]->value]);
    }
}
