<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Exists::class)]
#[Medium]
final class ExistsTest extends TestCase
{
    public function testDeriveScalarIsNeverNull(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $exists = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT EXISTS (SELECT NULL)')->find('expr')[0]);
        $derivation = new Derivation($platform->context($profile, null, [], true));

        self::assertInstanceOf(Exists::class, $exists);
        self::assertSame(Nullability::NotNull, $derivation->scalar($exists, $derivation->environment())->nullability);
    }

    public function testRenderWritesExistsAndTheQuery(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $exists = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT exists(select 1)')->find('expr')[0]);
        $out = new Output($platform->codec($profile));
        $exists->render($out);

        self::assertSame('EXISTS (SELECT 1)', (new Lexical())->join($out->pieces()));
    }
}
