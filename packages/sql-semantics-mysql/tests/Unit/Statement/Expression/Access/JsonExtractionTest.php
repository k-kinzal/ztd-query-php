<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonExtraction::class)]
#[Medium]
final class JsonExtractionTest extends TestCase
{
    public function testDeriveScalarIsJsonOrTextThatCanBeNull(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $json = $derivation->scalar(new JsonExtraction(new ColumnUse(new Name('a')), new Text('$.b')), $derivation->environment());
        $text = $derivation->scalar(new JsonExtraction(new ColumnUse(new Name('a')), new Text('$.b'), true), $derivation->environment());

        self::assertEquals([new Known(new Elementary(ElementaryKind::Json)), new Known(new Character(CharacterKind::LongText))], [$json->type, $text->type]);
        self::assertSame(Nullability::Nullable, $text->nullability);
    }

    public function testRenderWritesTheOperator(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-5.7.44', null, ParameterStyle::Native)));
        (new JsonExtraction(new ColumnUse(new Name('a')), new Text('$[0]'), true))->render($out);

        self::assertSame("a ->> '$[0]'", (new Lexical())->join($out->pieces()));
    }

    public function testAHexadecimalPathIsRejected(): void
    {
        $this->expectExceptionMessage('A JSON path is a quoted string.');

        new JsonExtraction(new ColumnUse(new Name('a')), new Text('24', EscapeRule::Backslash, Radix::Hexadecimal));
    }
}
