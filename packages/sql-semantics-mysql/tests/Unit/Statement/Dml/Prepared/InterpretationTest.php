<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(Interpretation::class)]
#[Small]
final class InterpretationTest extends TestCase
{
    public function testAllIncludesEverySwitchCombinationForEachClient(): void
    {
        $profiles = Interpretation::all(GrammarRelease::MySql847, ParameterStyle::Native);
        $counts = array_count_values(array_map(static fn (Interpretation $profile): string => $profile->client->name, $profiles));
        $keys = array_map(static fn (Interpretation $profile): string => $profile->key(), $profiles);
        self::assertCount(37, $counts);
        self::assertSame([32], array_values(array_unique($counts)));
        self::assertCount(1184, array_unique($keys));
        self::assertContains('11111:latin1', $keys);
        self::assertContains('00000:utf8mb4', $keys);
    }

    public function testKeyIncludesBothEffectiveFlagsAndClientEncoding(): void
    {
        $profile = new Interpretation(new LanguageProfile(GrammarRelease::MySql847, new \SqlSemantics\Contract\LexicalSettings('ANSI_QUOTES,IGNORE_SPACE')), Charset::known('latin1'));
        self::assertSame('10001:latin1', $profile->key());
    }
}
