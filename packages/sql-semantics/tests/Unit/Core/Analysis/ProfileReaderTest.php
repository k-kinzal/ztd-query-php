<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\ProfileReader;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Statement\Contract\GrammarRelease;
use SqlSemantics\Statement\Contract\ParameterStyle;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(ProfileReader::class)]
#[Medium]
final class ProfileReaderTest extends TestCase
{
    public function testReadCopiesOnlyFixedSemanticSettingsAndArtifactIdentity(): void
    {
        $language = new Language(Dialect::MySql, 'mysql-8.4.7', Mode::fromString('ANSI_QUOTES'), Parameters::Named);
        $profile = (new ProfileReader())->read($language->version, 'ANSI_QUOTES', ParameterStyle::Named);
        self::assertSame(GrammarRelease::MySql847, $profile->grammar);
        self::assertTrue($profile->lexical->ansiQuotes);
        self::assertSame(ParameterStyle::Named, $profile->parameters);
        self::assertTrue((new SemanticGraph())->containsOnlyValues($profile));
    }
}
