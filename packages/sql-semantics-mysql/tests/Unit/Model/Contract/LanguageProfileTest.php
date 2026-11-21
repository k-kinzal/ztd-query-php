<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;

#[CoversClass(LanguageProfile::class)]
#[Small]
final class LanguageProfileTest extends TestCase
{
    public function testCompatibleWithAcceptsAProfileOfTheSameArtifactsAndSettings(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql8044, new LexicalSettings(), ParameterStyle::Named);
        $other = new LanguageProfile(GrammarRelease::MySql8044, new LexicalSettings(), ParameterStyle::Named);

        self::assertTrue($profile->compatibleWith($other));
        self::assertSame('SQLSEM-DESIGN-001/1.0', $profile->ruleRevision);
    }

    public function testCompatibleWithRejectsAnotherParameterStyle(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql8044);

        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::MySql8044, new LexicalSettings(), ParameterStyle::Named)));
    }

    public function testCompatibleWithRejectsAnotherGrammarRelease(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql8044);

        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::MySql847)));
    }

    public function testCompatibleWithRejectsOtherLexicalSettings(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql8044, new LexicalSettings('ANSI_QUOTES'));

        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::MySql8044)));
    }
}
