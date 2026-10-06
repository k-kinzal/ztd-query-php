<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(CharsetConversion::class)]
#[Medium]
final class CharsetConversionTest extends TestCase
{
    public function testDeriveScalarIsAStringOfTheCharacterSet(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertEquals(new Known(new Character(CharacterKind::VarChar, null, false, new CharsetAttribute(CharsetForm::Named, new Name('latin1')))), $derivation->scalar(new CharsetConversion(new StringLiteral(['x']), new CharsetName(new Name('latin1'))), $derivation->environment())->type);
        self::assertEquals(new Known(new Binary(BinaryKind::VarBinary)), $derivation->scalar(new CharsetConversion(new StringLiteral(['x']), new CharsetName(new Name('BINARY'))), $derivation->environment())->type);
    }

    public function testRenderWritesUsing(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new CharsetConversion(new StringLiteral(['x']), new CharsetName(new Name('utf8mb4'))))->render($out);

        self::assertSame("CONVERT('x' USING utf8mb4)", (new Lexical())->join($out->pieces()));
    }

    public function testTheDefaultCharacterSetIsRejected(): void
    {
        $this->expectExceptionMessage('CONVERT … USING names a character set.');

        new CharsetConversion(new StringLiteral(['x']), new CharsetName(null));
    }
}
