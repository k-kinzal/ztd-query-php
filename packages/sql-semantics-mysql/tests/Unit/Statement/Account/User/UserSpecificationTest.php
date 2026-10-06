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
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;

#[CoversClass(UserSpecification::class)]
#[Medium]
final class UserSpecificationTest extends TestCase
{
    public function testGrantedAcceptsANamedAccountWithItsFirstMethod(): void
    {
        self::assertTrue((new UserSpecification(new CurrentUser(), new Identification(null, Credential::Password, new Text('x'))))->granted());
        self::assertFalse((new UserSpecification(new CurrentUser(), null, [], null, null, false, true))->granted());
    }

    public function testCreatedRefusesPasswordManagementWords(): void
    {
        self::assertTrue((new UserSpecification(new CurrentUser(), null, [new Identification(null, Credential::RandomPassword)]))->created());
        self::assertFalse((new UserSpecification(new SessionUser(), null, [], null, null, false, true))->created());
    }

    public function testAlteredRefusesFurtherFactors(): void
    {
        self::assertTrue((new UserSpecification(new CurrentUser()))->altered());
        self::assertFalse((new UserSpecification(new CurrentUser(), null, [new Identification(null, Credential::RandomPassword)]))->altered());
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("CREATE USER u IDENTIFIED WITH p INITIAL AUTHENTICATION IDENTIFIED WITH q AS 'h'", (new Semantics(Dialect::MySql))->analyze("create user u identified with p initial authentication identified with q as 'h'")->toString());
        self::assertSame("ALTER USER u IDENTIFIED BY RANDOM PASSWORD REPLACE 'c' RETAIN CURRENT PASSWORD", (new Semantics(Dialect::MySql))->analyze("alter user u identified by random password replace 'c' retain current password")->toString());
    }
}
