<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\UserStatementRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UserStatementRule::class)]
#[Medium]
final class UserStatementRuleTest extends TestCase
{
    public function testDefinitionLowersEveryRoutedForm(): void
    {
        self::assertSame('CREATE USER IF NOT EXISTS u REQUIRE SSL WITH MAX_USER_CONNECTIONS 1 ACCOUNT LOCK', (new Inspection('mysql-5.7.44'))->inspect('create user if not exists u require ssl with max_user_connections 1 account lock')->toString());
        self::assertSame('DROP USER IF EXISTS u', (new Inspection('mysql-5.7.44'))->inspect('drop user if exists u')->toString());
    }

    public function testSkippedConfirmsTheMarker(): void
    {
        self::assertSame('CREATE USER u', (new Inspection('mysql-5.6.51'))->inspect('create user u')->toString());
    }

    public function testAlterLowersEveryForm(): void
    {
        self::assertSame('ALTER USER USER() IDENTIFIED BY RANDOM PASSWORD RETAIN CURRENT PASSWORD', (new Inspection())->inspect('alter user user() identified by random password retain current password')->toString());
        self::assertSame('ALTER USER USER() DISCARD OLD PASSWORD', (new Inspection())->inspect('alter user user() discard old password')->toString());
    }

    public function testCommandLowersIfExists(): void
    {
        self::assertSame('ALTER USER IF EXISTS u', (new Inspection())->inspect('alter user if exists u')->toString());
    }

    public function testSessionLowersUserFunction(): void
    {
        self::assertSame("ALTER USER USER() IDENTIFIED BY 'x'", (new Inspection('mysql-5.7.44'))->inspect("alter user user() identified by 'x'")->toString());
    }

    public function testRegistrationLowersEveryStep(): void
    {
        self::assertSame('ALTER USER u 2 FACTOR INITIATE REGISTRATION', (new Inspection())->inspect('alter user u 2 factor initiate registration')->toString());
    }

    public function testDefaultRolesLowersTheClause(): void
    {
        self::assertSame('CREATE USER u DEFAULT ROLE r1, r2', (new Inspection())->inspect('create user u default role r1, r2')->toString());
    }

    public function testExpiredLowersTheLegacyList(): void
    {
        self::assertSame('ALTER USER a PASSWORD EXPIRE, b PASSWORD EXPIRE, c PASSWORD EXPIRE', (new Inspection('mysql-5.6.51'))->inspect('alter user a password expire, b password expire, c password expire')->toString());
    }

    public function testDropLowersTheStatement(): void
    {
        self::assertSame('DROP USER IF EXISTS a, b', (new Inspection())->inspect('drop user if exists a, b')->toString());
    }

    public function testRenameLowersEveryPair(): void
    {
        self::assertSame('RENAME USER a TO b, c TO d, e TO f', (new Inspection('mysql-5.6.51'))->inspect('rename user a to b, c to d, e to f')->toString());
    }
}
