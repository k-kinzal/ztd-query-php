<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Configuration\Replication\Source\SourceText;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SourceText::class)]
#[Medium]
final class SourceTextTest extends TestCase
{
    public function testOptionReturnsTheAssignedOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_TLS_CIPHERSUITES = NULL, SOURCE_COMPRESSION_ALGORITHMS = 'zlib'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(SourceText::class, $statement->settings[0]);
        self::assertSame(SourceOption::TlsCiphersuites, $statement->settings[0]->option());
        self::assertSame('NULL', $statement->settings[0]->value->text);
    }

    public function testRejectsANumericOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(SourceText::class, $statement->settings[0]);
        $this->expectException(InvalidStructure::class);
        new SourceText(SourceOption::Port, $statement->settings[0]->value);
    }

    public function testRejectsNullForAnotherOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_TLS_CIPHERSUITES = NULL');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(SourceText::class, $statement->settings[0]);
        $this->expectException(InvalidStructure::class);
        new SourceText(SourceOption::Host, $statement->settings[0]->value);
    }
}
