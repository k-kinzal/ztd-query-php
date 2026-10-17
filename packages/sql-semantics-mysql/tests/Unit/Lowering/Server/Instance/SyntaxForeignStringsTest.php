<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\SyntaxForeignStrings;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(SyntaxForeignStrings::class)]
#[Medium]
final class SyntaxForeignStringsTest extends TestCase
{
    public function testConvertedKeepsCompletedOperandsBeforeInvalidSyntax(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $parser = new MySqlParser($profile->grammar->value);
        $sql = "CREATE SERVER s FOREIGN DATA WRAPPER '😀' OPTIONS(HOST '猫😀', broken)";
        $notices = (new SyntaxWarnings())->completed($parser, $sql, strlen($sql), $profile, client: Charset::known('utf8mb4'));

        self::assertCount(2, $notices);
        self::assertSame(['😀', '猫😀'], array_map(static fn (array $notice): string => $notice[1] instanceof NameConversion ? $notice[1]->name->value : '', $notices));
        self::assertSame([], (new SyntaxWarnings())->completed($parser, $sql, strlen($sql), $profile, client: Charset::known('latin1')));
    }

    public function testNameRecognizesOnlyForeignServerNamePositions(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $parser = new MySqlParser($profile->grammar->value);
        $sql = "CREATE SERVER '😀' FOREIGN DATA WRAPPER '猫😀' OPTIONS(HOST 'x')";
        $notices = (new SyntaxWarnings())->completed($parser, $sql, strlen($sql), $profile);

        self::assertCount(2, $notices);
        self::assertSame(['😀', '猫😀'], array_map(static fn (array $notice): string => $notice[1] instanceof NameConversion ? $notice[1]->name->value : '', $notices));
        self::assertSame([], (new SyntaxWarnings())->completed($parser, "SELECT '😀'", 13, $profile));
    }
}
