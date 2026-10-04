<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastAtLocal;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(CastAtLocal::class)]
#[Medium]
final class CastAtLocalTest extends TestCase
{
    public function testDeriveScalarReportsTheUnsupportedForm(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new CastAtLocal(new NumberLiteral('1'), new CastTarget(CastKind::DateTime)), $derivation->environment());

        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertSame("This version of MySQL doesn't yet support 'AT LOCAL'.", $derivation->facts()->diagnostics[0]->message());
    }

    public function testRenderWritesAtLocal(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new CastAtLocal(new NullLiteral(), new CastTarget(CastKind::Signed), true))->render($out);

        self::assertSame('CAST(NULL AT LOCAL AS SIGNED ARRAY)', (new Lexical())->join($out->pieces()));
    }
}
