<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Clones;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Administration\CloneEncryption;
use SqlSemantics\Model\Statement\Server\Administration\CloneRemoteStatement;
use SqlSemantics\Model\Statement\Server\CloneLocalStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Clones::class)]
#[Medium]
final class ClonesTest extends TestCase
{
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsTheDonorAndOptions(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p' DATA DIRECTORY = '/d' REQUIRE SSL");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        self::assertInstanceOf(AccountName::class, $statement->donor);
        self::assertSame('h', $statement->donor->host);
        self::assertSame('3306', $statement->port->text);
        self::assertSame("'/d'", $statement->directory?->text);
        self::assertSame(CloneEncryption::Required, $statement->encryption);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
        self::assertInstanceOf(CloneLocalStatement::class, $binder->bind("CLONE LOCAL DATA DIRECTORY '/x'"));
    }

    public function testDonorKeepsCurrentUserUnresolved(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM CURRENT_USER():1 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        self::assertSame(CurrentAccount::Authenticated, $statement->donor);
        self::assertSame("CLONE INSTANCE FROM CURRENT_USER:1 IDENTIFIED BY 'p'", $statement->toString());
    }
}
