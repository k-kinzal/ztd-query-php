<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewDefiner;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewDefinerLowering;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;

#[CoversClass(ViewDefinerLowering::class)]
#[Small]
final class ViewDefinerLoweringTest extends TestCase
{
    public function testLowerResolvesAValidLegacyAccountAndItsHostKey(): void
    {
        $lowering = new ViewDefinerLowering();
        $account = $lowering->lower(new AccountName(new Name('Reader'), new Name(' ÉXAMPLE.TEST ')), GrammarRelease::MySql5744);

        self::assertInstanceOf(ViewDefiner::class, $account);
        self::assertSame('Reader', $account->user);
        self::assertSame('éxample.test', $account->host);
    }

    public function testLowerKeepsTheNativeSessionAccountRule(): void
    {
        $lowering = new ViewDefinerLowering();

        self::assertNull($lowering->lower(null, GrammarRelease::MySql910));
        self::assertNull($lowering->lower(new CurrentUser(), GrammarRelease::MySql910));
    }
}
