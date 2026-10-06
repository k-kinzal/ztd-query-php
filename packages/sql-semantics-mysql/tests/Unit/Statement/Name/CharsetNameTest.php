<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\PieceKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CharsetName::class)]
#[Small]
final class CharsetNameTest extends TestCase
{
    public function testRenderWritesTheNameAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new CharsetName(new Name('utf8mb4')))->render($out);
        (new CharsetName(new Name('UTF8MB4')))->render($out);

        self::assertSame('utf8mb4 UTF8MB4', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordDefaultForAnAbsentName(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new CharsetName(null))->render($out);
        $pieces = $out->pieces();

        self::assertCount(1, $pieces);
        self::assertSame(PieceKind::Keyword, $pieces[0]->kind);
        self::assertSame('DEFAULT', $pieces[0]->text);
    }

    public function testLoweredCharsetReadsTheBinaryKeywordAndAQuotedStringAsNames(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $binary = $lowering->charsets->charset($parser->parse('SELECT CONVERT(a USING binary)')->find('charset_name')[0]);
        $quoted = $lowering->charsets->charset($parser->parse("SELECT CONVERT(a USING 'latin1')")->find('charset_name')[0]);
        $out = new Output($platform->codec($profile));
        $binary->render($out);

        self::assertSame('binary', $binary->name?->value);
        self::assertSame('latin1', $quoted->name?->value);
        self::assertSame('`binary`', (new Lexical())->join($out->pieces()));
    }

    public function testLoweredDefaultHasNoName(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('ALTER DATABASE d CHARACTER SET = DEFAULT')->find('charset_name_or_default')[0];
        $charset = (new Lowering($platform->productions($profile), new Leaves(), $profile))->charsets->charset($node);

        self::assertNull($charset->name);
    }
}
