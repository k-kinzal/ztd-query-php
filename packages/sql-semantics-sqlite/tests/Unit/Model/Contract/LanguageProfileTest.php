<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\Sqlite\Model\Contract\ParameterStyle;

#[CoversClass(LanguageProfile::class)]
#[Small]
final class LanguageProfileTest extends TestCase
{
    public function testCompatibleWithAcceptsAProfileOfTheSameArtifactsAndSettings(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings(), ParameterStyle::Named);
        $other = new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings(), ParameterStyle::Named);

        self::assertTrue($profile->compatibleWith($other));
        self::assertSame('SQLSEM-DESIGN-001/1.0', $profile->ruleRevision);
    }

    public function testCompatibleWithRejectsAnotherParameterStyle(): void
    {
        $profile = new LanguageProfile(GrammarRelease::Sqlite3472);

        self::assertFalse($profile->compatibleWith(new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings(), ParameterStyle::Named)));
    }

    public function testCompatibleWithIsOnlyDefinedForProfilesThatAcceptTheirSettings(): void
    {
        $this->expectExceptionMessage('Only the MySQL profiles accept MySQL lexical settings.');

        new LanguageProfile(GrammarRelease::Sqlite3472, new LexicalSettings('PIPES_AS_CONCAT'));
    }
}
