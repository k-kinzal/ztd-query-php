<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Authentication;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Authentication\DefinerReferences;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefinerReferences::class)]
#[Medium]
final class DefinerReferencesTest extends TestCase
{
    public function testRecordReportsEachMatchingFamilyOnceInPreparationOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; CREATE DEFINER='owner'@'%' VIEW d.v AS SELECT 1; CREATE DEFINER='owner'@'%' VIEW d.w AS SELECT 2; CREATE DEFINER='owner'@'%' EVENT d.e ON SCHEDULE EVERY 1 DAY DISABLE DO SET @x=1");
        $session->diagnostics->clear();
        (new DefinerReferences())->record(Identity::of('owner', '%'), $session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([['Warning', 4005, "User 'owner'@'%' is referenced as a definer account in an event."], ['Warning', 4005, "User 'owner'@'%' is referenced as a definer account in a view."]], $session->diagnostics->conditions);
    }

    public function testRecordKeepsLegacyAccountChangesSilent(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("CREATE DATABASE d; CREATE DEFINER='owner'@'%' VIEW d.v AS SELECT 1");
        $session->diagnostics->clear();
        (new DefinerReferences())->record(Identity::of('owner', '%'), $session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([], $session->diagnostics->conditions);
    }
}
