<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\CredentialOption;
use SqlSemantics\Model\Configuration\Replication\ReplicationCredential;
use SqlSemantics\Model\Configuration\Replication\SourcePosition;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplicationCredential::class)]
#[Medium]
final class ReplicationCredentialTest extends TestCase
{
    public function testTextDecodesTheValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA USER = 'it''s'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertSame(CredentialOption::User, $statement->credentials[0]->option);
        self::assertSame("it's", $statement->credentials[0]->text());
    }

    public function testRejectsANumericValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA UNTIL SOURCE_LOG_FILE = 'f', SOURCE_LOG_POS = 4");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(SourcePosition::class, $statement->until);
        $this->expectException(InvalidStructure::class);
        new ReplicationCredential(CredentialOption::Password, $statement->until->position);
    }
}
