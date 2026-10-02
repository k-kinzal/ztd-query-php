<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Contract\GrammarRelease;
use SqlSemantics\Statement\Contract\LanguageProfile;
use SqlSemantics\Statement\Contract\LexicalSettings;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(LanguageProfile::class)]
#[Small]
final class LanguageProfileTest extends TestCase
{
    public function testCompatibleWithRequiresTheExactVersionAndMode(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        self::assertTrue($profile->compatibleWith(new LanguageProfile(GrammarRelease::MySql847)));
        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::MySql8044)));
        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::PostgreSql172)));
        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::MySql847, new LexicalSettings('ANSI_QUOTES'))));
        self::assertTrue((new SemanticGraph())->containsOnlyValues($profile));
    }

    public function testForeignDialectSettingsAreRejectedBeforePublication(): void
    {
        $this->expectException(InvalidConstruction::class);
        new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings('ANSI_QUOTES'));
    }
}
