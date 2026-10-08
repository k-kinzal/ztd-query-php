<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(Deprecated::class)]
#[Small]
final class DeprecatedTest extends TestCase
{
    public function testCodeNamesTheErrorOfEachKindOfWarning(): void
    {
        self::assertSame(1287, Deprecated::PipesOr->code());
        self::assertSame(3005, Deprecated::ReplaceDelayed->code());
        self::assertSame(1681, Deprecated::Zerofill->code());
        self::assertSame(3719, Deprecated::Utf8Alias->code());
        self::assertSame(3962, Deprecated::IntoInsideQuery->code());
    }

    public function testWarnedInLimitsReleasesBeforeEightToDelayed(): void
    {
        self::assertTrue(Deprecated::InsertDelayed->warnedIn(GrammarRelease::MySql5744));
        self::assertFalse(Deprecated::AmpersandsAnd->warnedIn(GrammarRelease::MySql5651));
        self::assertTrue(Deprecated::AmpersandsAnd->warnedIn(GrammarRelease::MySql847));
    }
}
