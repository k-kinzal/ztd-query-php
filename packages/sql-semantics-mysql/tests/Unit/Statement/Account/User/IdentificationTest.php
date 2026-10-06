<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\Identification;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Identification::class)]
#[Medium]
final class IdentificationTest extends TestCase
{
    public function testPasswordTellsADefaultPluginPassword(): void
    {
        self::assertTrue((new Identification(null, Credential::RandomPassword))->password());
        self::assertFalse((new Identification(new Name('p'), Credential::Password, new Text('x')))->password());
    }

    public function testReplaceableTellsANewPassword(): void
    {
        self::assertTrue((new Identification(new Name('p'), Credential::Password, new Text('x')))->replaceable());
        self::assertFalse((new Identification(new Name('p'), Credential::RandomPassword))->replaceable());
    }

    public function testRetainableTellsANewSecret(): void
    {
        self::assertTrue((new Identification(new Name('p'), Credential::Hash, new Text('h')))->retainable());
        self::assertFalse((new Identification(new Name('p'), Credential::None))->retainable());
    }

    public function testInitialTellsAnInitialAuthenticationMethod(): void
    {
        self::assertTrue((new Identification(null, Credential::Password, new Text('x')))->initial());
        self::assertFalse((new Identification(new Name('p'), Credential::Password, new Text('x')))->initial());
    }

    public function testRenderWritesEveryCredential(): void
    {
        self::assertSame("CREATE USER a IDENTIFIED WITH p BY RANDOM PASSWORD, b IDENTIFIED WITH p AS 'h', c IDENTIFIED WITH p BY 'x', d IDENTIFIED BY RANDOM PASSWORD", (new Semantics(Dialect::MySql))->analyze("create user a identified with p by random password, b identified with 'p' as 'h', c identified with p by 'x', d identified by random password")->toString());
        self::assertSame("GRANT USAGE ON *.* TO a IDENTIFIED BY PASSWORD 'h'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("grant usage on *.* to a identified by password 'h'")->toString());
    }
}
