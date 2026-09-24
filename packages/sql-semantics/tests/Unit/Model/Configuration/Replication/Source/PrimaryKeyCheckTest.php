<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Source\PrimaryKeyCheck;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrimaryKeyCheck::class)]
#[Medium]
final class PrimaryKeyCheckTest extends TestCase
{
    public function testOptionIsRequireTablePrimaryKeyCheck(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO REQUIRE_TABLE_PRIMARY_KEY_CHECK = stream');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertSame([PrimaryKeyCheck::Stream], $statement->settings);
        self::assertSame(SourceOption::RequireTablePrimaryKeyCheck, PrimaryKeyCheck::Off->option());
    }
}
