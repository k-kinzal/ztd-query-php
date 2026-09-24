<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsBeforeStatement;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsToStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplicationText::class)]
#[Medium]
final class ReplicationTextTest extends TestCase
{
    public function testCheckReturnsTheDecodedText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'a''b\\nc'");
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $statement);
        self::assertSame("a'b\nc", ReplicationText::check($statement->logName, 'name'));
        $this->expectException(InvalidStructure::class);
        ReplicationText::check($statement->logName, 'name', true);
    }

    public function testCheckRejectsANumber(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('PURGE BINARY LOGS BEFORE 1');
        self::assertInstanceOf(PurgeBinaryLogsBeforeStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $statement->moment);
        $this->expectException(InvalidStructure::class);
        ReplicationText::check($statement->moment, 'name');
    }

    public function testDecodeResolvesQuotesAndEscapes(): void
    {
        self::assertSame("it's", ReplicationText::decode("'it''s'"));
        self::assertSame("a\tb\\%", ReplicationText::decode("'a\\tb\\%'"));
        self::assertSame('say "x"', ReplicationText::decode('"say ""x"""'));
    }
}
