<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\Source\AnonymousGtidUuid;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AnonymousGtidUuid::class)]
#[Medium]
final class AnonymousGtidUuidTest extends TestCase
{
    public function testOptionIsAssignGtidsToAnonymousTransactions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = '3E11FA47-71CA-11E1-9E33-C80AA9429562'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(AnonymousGtidUuid::class, $statement->settings[0]);
        self::assertSame(SourceOption::AssignGtidsToAnonymousTransactions, $statement->settings[0]->option());
    }

    public function testRejectsTextThatIsNotAUuid(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ReplicationOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = 'x'");
    }
}
