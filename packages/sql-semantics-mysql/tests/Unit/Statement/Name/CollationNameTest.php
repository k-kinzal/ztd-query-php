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
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\PieceKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CollationName::class)]
#[Small]
final class CollationNameTest extends TestCase
{
    public function testRenderWritesTheNameAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new CollationName(new Name('utf8mb4_bin')))->render($out);
        (new CollationName(new Name('Latin1_General_CS')))->render($out);

        self::assertSame('utf8mb4_bin Latin1_General_CS', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordDefaultForAnAbsentName(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new CollationName(null))->render($out);
        $pieces = $out->pieces();

        self::assertCount(1, $pieces);
        self::assertSame(PieceKind::Keyword, $pieces[0]->kind);
        self::assertSame('DEFAULT', $pieces[0]->text);
    }

    public function testLoweredCollationReadsAQuotedStringAndTheBinaryKeywordAsNames(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $plain = $lowering->charsets->collation($parser->parse('SET NAMES utf8 COLLATE utf8_bin')->find('opt_collate')[0]);
        $quoted = $lowering->charsets->collation($parser->parse("SET NAMES utf8 COLLATE 'utf8_bin'")->find('opt_collate')[0]);
        $binary = $lowering->charsets->collation($parser->parse('SET NAMES utf8 COLLATE binary')->find('opt_collate')[0]);
        $out = new Output($platform->codec($profile));
        $binary?->render($out);

        self::assertSame('utf8_bin', $plain?->name?->value);
        self::assertSame('utf8_bin', $quoted?->name?->value);
        self::assertSame('binary', $binary?->name?->value);
        self::assertSame('`binary`', (new Lexical())->join($out->pieces()));
    }

    public function testLoweredDefaultHasNoName(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('ALTER DATABASE d COLLATE = DEFAULT')->find('collation_name_or_default')[0];
        $collation = (new Lowering($platform->productions($profile), new Leaves(), $profile))->charsets->collation($node);

        self::assertNotNull($collation);
        self::assertNull($collation->name);
    }
}
