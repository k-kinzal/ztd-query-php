<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Source\SourceFlag;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SourceFlag::class)]
#[Medium]
final class SourceFlagTest extends TestCase
{
    public function testOptionReturnsTheAssignedOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_SSL = 0.5, GET_SOURCE_PUBLIC_KEY = 0x0f');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertEquals([new SourceFlag(SourceOption::Ssl, false), new SourceFlag(SourceOption::GetPublicKey, true)], $statement->settings);
        self::assertSame(SourceOption::Ssl, $statement->settings[0]->option());
    }

    public function testRejectsAnOptionWithAValue(): void
    {
        $this->expectException(InvalidStructure::class);
        new SourceFlag(SourceOption::Port, true);
    }

    public function testRejectsAnOptionWithAValueByName(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage(SourceOption::Port->value . ' is not switched on or off.');
        new SourceFlag(SourceOption::Port, true);
    }
}
