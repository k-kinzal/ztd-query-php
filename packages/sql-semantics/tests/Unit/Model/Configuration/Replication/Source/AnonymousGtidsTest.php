<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Source\AnonymousGtids;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AnonymousGtids::class)]
#[Medium]
final class AnonymousGtidsTest extends TestCase
{
    public function testOptionIsAssignGtidsToAnonymousTransactions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = OFF');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertSame([AnonymousGtids::Off], $statement->settings);
        self::assertSame(SourceOption::AssignGtidsToAnonymousTransactions, AnonymousGtids::Local->option());
    }
}
