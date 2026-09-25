<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Administration\CloneEncryption;
use SqlSemantics\Model\Statement\Server\Administration\CloneRemoteStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CloneRemoteStatement::class)]
#[Medium]
final class CloneRemoteStatementTest extends TestCase
{
    public function testWithOriginPreservesEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM u@h:3306 IDENTIFIED BY 'p' DATA DIRECTORY '/d' REQUIRE NO SSL");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame(CloneEncryption::Refused, $copy->encryption);
        self::assertSame("CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p' DATA DIRECTORY = '/d' REQUIRE NO SSL", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithDonorReplacesAccountAndPort(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM u@h:3306 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        $changed = $statement->withDonor(CurrentAccount::Authenticated, $statement->port);
        self::assertSame(CurrentAccount::Authenticated, $changed->donor);
        self::assertInstanceOf(AccountName::class, $statement->donor);
        self::assertSame('u', $statement->donor->username);
    }

    public function testWithPasswordReplacesTheCredential(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p'");
        $other = $binder->bind("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'q' DATA DIRECTORY '/d'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        self::assertInstanceOf(CloneRemoteStatement::class, $other);
        self::assertSame("'q'", $statement->withPassword($other->password)->password->text);
        self::assertSame("'p'", $statement->password->text);
    }

    public function testWithDirectoryReplacesTheDestination(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p' DATA DIRECTORY '/d'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        self::assertNull($statement->withDirectory(null)->directory);
        self::assertNotNull($statement->directory);
    }

    public function testWithEncryptionReplacesTheRequirement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        self::assertStringEndsWith('REQUIRE SSL', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withEncryption(CloneEncryption::Required)));
        self::assertNull($statement->encryption);
    }

    public function testRejectsATextPort(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CloneRemoteStatement($statement->origin, $statement->donor, $statement->password, $statement->password);
    }
}
