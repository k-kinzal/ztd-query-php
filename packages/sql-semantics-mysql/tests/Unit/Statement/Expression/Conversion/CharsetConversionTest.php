<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(CharsetConversion::class)]
#[Medium]
final class CharsetConversionTest extends TestCase
{
    public function testDeriveScalarPreservesOperandNullabilityOnMySql56(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.6.51', null, ParameterStyle::Native), null, [], true));
        $conversion = new CharsetConversion(new StringLiteral(['x']), new CharsetName(new Name('utf16')));

        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $derivation->scalar($conversion, $derivation->environment())->nullability);
    }

    public function testDeriveScalarIsAStringOfTheCharacterSet(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertEquals(new Known(Domain::string(1, Collation::known('latin1_swedish_ci'))), $derivation->scalar(new CharsetConversion(new StringLiteral(['x']), new CharsetName(new Name('latin1'))), $derivation->environment())->type);
        self::assertEquals(new Known(Domain::string(1, Collation::binary())), $derivation->scalar(new CharsetConversion(new StringLiteral(['x']), new CharsetName(new Name('BINARY'))), $derivation->environment())->type);
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

    public function testDeriveScalarWarnsAboutUtf8mb3(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT CONVERT('a' USING utf8mb3)");

        self::assertEquals([new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Utf8mb3)], $operation->facts->warnings);
    }


    /**
     * @return iterable<string, array{\SqlSemantics\Statement\Scalar, string}>
     */
    public static function providerUnknownCharsets(): iterable
    {
        yield 'empty name and known operand' => [new StringLiteral(['x']), ''];
        yield 'unknown name and known operand' => [new StringLiteral(['x']), 'unknown_charset'];
        yield 'empty name and unresolved operand' => [new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new Name('missing')), ''];
        yield 'unknown name and unresolved operand' => [new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new Name('missing')), 'unknown_charset'];
    }

    #[DataProvider('providerUnknownCharsets')]
    public function testDeriveScalarDiagnosesCharsetIndependentlyOfOperandType(\SqlSemantics\Statement\Scalar $operand, string $name): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $conversion = new CharsetConversion($operand, new CharsetName(new Name($name)));

        $derivation->scalar($conversion, $derivation->environment());

        $problems = array_values(array_filter($derivation->facts()->diagnostics, static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset));
        self::assertEquals([new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset($name)], $problems);
    }

}
