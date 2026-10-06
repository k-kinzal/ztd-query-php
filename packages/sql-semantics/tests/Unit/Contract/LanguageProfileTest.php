<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Contract\ParameterStyle;

#[CoversClass(LanguageProfile::class)]
#[Small]
final class LanguageProfileTest extends TestCase
{
    public function testCompatibleWithAcceptsAProfileOfTheSameArtifactsAndSettings(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql8044, new LexicalSettings('ANSI_QUOTES'), ParameterStyle::Named);
        $other = new LanguageProfile(GrammarRelease::MySql8044, new LexicalSettings('ANSI_QUOTES'), ParameterStyle::Named);

        self::assertTrue($profile->compatibleWith($other));
        self::assertSame('SQLSEM-DESIGN-001/1.0', $profile->ruleRevision);
    }

    public function testCompatibleWithRejectsAnotherGrammarRelease(): void
    {
        $profile = new LanguageProfile(GrammarRelease::PostgreSql166);

        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::PostgreSql172)));
    }

    public function testCompatibleWithRejectsAnotherParameterStyle(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);

        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings(), ParameterStyle::Named)));
    }

    public function testCompatibleWithRejectsOtherLexicalSettings(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql8044, new LexicalSettings('ANSI_QUOTES'));

        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::MySql8044)));
    }

    public function testCompatibleWithIsOnlyDefinedForProfilesThatAcceptTheirSettings(): void
    {
        $this->expectExceptionMessage('Only the MySQL profiles accept MySQL lexical settings.');

        new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings('PIPES_AS_CONCAT'));
    }
}
