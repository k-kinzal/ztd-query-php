<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

#[CoversClass(OdbcEscape::class)]
#[Medium]
final class OdbcEscapeTest extends TestCase
{
    public function testDeriveScalarReadsATemporalLiteralForTheTemporalKinds(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $timestamp = $derivation->scalar(new OdbcEscape(new Name('TS'), new StringLiteral(['2024-01-31 10:00:00'])), $derivation->environment());
        $other = $derivation->scalar(new OdbcEscape(new Name('fn'), new NumberLiteral('1')), $derivation->environment());

        self::assertEquals(new Known(new Temporal(TemporalKind::DateTime)), $timestamp->type);
        self::assertInstanceOf(Known::class, $other->type);
        self::assertSame('BIGINT', $other->type->descriptor->name());
    }

    public function testRenderWritesTheBraces(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new OdbcEscape(new Name('t'), new StringLiteral(['10:00'])))->render($out);

        self::assertSame("{ t '10:00' }", (new Lexical())->join($out->pieces()));
    }
}
