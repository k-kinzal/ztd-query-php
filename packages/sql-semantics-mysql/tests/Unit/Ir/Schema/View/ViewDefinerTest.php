<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewDefiner;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;

#[CoversClass(ViewDefiner::class)]
#[Small]
final class ViewDefinerTest extends TestCase
{
    public function testIdentityKeepsTheUserCaseAndFoldsTheHost(): void
    {
        $account = new ViewDefiner('  Reporter@West ', ' ÉXAMPLE.TEST ');

        self::assertSame('Reporter@West', $account->user);
        self::assertSame('éxample.test', $account->host);
        self::assertSame("'Reporter@West'@'éxample.test'", $account->accountSql());
    }

    public function testAccountSqlDistinguishesAnExplicitEmptyHost(): void
    {
        self::assertSame("'Reporter'@'%'", (new ViewDefiner('Reporter'))->accountSql());
        self::assertSame("'Reporter'@''", (new ViewDefiner('Reporter', ''))->accountSql());
    }

    public function testCheckReleaseAcceptsTheExactLegacyWidths(): void
    {
        $host = str_repeat('棚', 20);
        $account = new ViewDefiner(str_repeat('é', 16), $host);
        $account->checkRelease(GrammarRelease::MySql5651);

        self::assertSame($host, $account->host);
    }

    public function testLegacyHostRejectsTheFirstByteBeyondItsLimit(): void
    {
        $account = new ViewDefiner('Reader', 'a' . str_repeat('棚', 20));

        $this->expectException(InvalidConstruction::class);
        $account->checkRelease(GrammarRelease::MySql5744);
    }

    public function testModernHostRejectsTheFirstByteBeyondItsLimit(): void
    {
        $this->expectException(InvalidConstruction::class);
        new ViewDefiner('Reader', 'a' . str_repeat('棚', 85));
    }

    public function testHostRejectsTheAccountDelimiter(): void
    {
        $this->expectException(InvalidConstruction::class);
        new ViewDefiner('Reader', 'local@host');
    }
}
