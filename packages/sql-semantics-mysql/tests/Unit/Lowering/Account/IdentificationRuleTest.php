<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\IdentificationRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(IdentificationRule::class)]
#[Medium]
final class IdentificationRuleTest extends TestCase
{
    public function testIdentificationLowersEveryForm(): void
    {
        self::assertSame("CREATE USER u IDENTIFIED WITH p BY 'x'", (new Inspection())->inspect("create user u identified with p by 'x'")->toString());
        self::assertSame("CREATE USER u IDENTIFIED WITH p AS x'41'", (new Inspection())->inspect('create user u identified with p as 0x41')->toString());
    }

    public function testFactorsLowersTheFurtherFactors(): void
    {
        self::assertSame("CREATE USER u IDENTIFIED BY 'a' AND IDENTIFIED WITH p AND IDENTIFIED WITH q", (new Inspection())->inspect("create user u identified by 'a' and identified with p and identified with q")->toString());
    }

    public function testInitialLowersTheInitialAuthentication(): void
    {
        self::assertSame("CREATE USER u IDENTIFIED WITH p INITIAL AUTHENTICATION IDENTIFIED BY 'x'", (new Inspection())->inspect("create user u identified with p initial authentication identified by 'x'")->toString());
    }

    public function testReplaceLowersTheCurrentPassword(): void
    {
        self::assertSame("ALTER USER USER() IDENTIFIED BY 'n' REPLACE 'o'", (new Inspection())->inspect("alter user user() identified by 'n' replace 'o'")->toString());
    }

    public function testRetainLowersTheKeyword(): void
    {
        self::assertSame("ALTER USER u IDENTIFIED WITH p AS 'h' RETAIN CURRENT PASSWORD", (new Inspection())->inspect("alter user u identified with p as 'h' retain current password")->toString());
    }

    public function testDiscardLowersTheKeyword(): void
    {
        self::assertSame('ALTER USER u DISCARD OLD PASSWORD', (new Inspection())->inspect('alter user u discard old password')->toString());
    }

    public function testFactorKeepsTheNumberAsWritten(): void
    {
        self::assertSame('ALTER USER u 3 FACTOR UNREGISTER', (new Inspection())->inspect('alter user u 3 factor unregister')->toString());
    }
}
