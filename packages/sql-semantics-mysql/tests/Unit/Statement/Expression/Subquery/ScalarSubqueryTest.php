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
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ScalarSubquery::class)]
#[Medium]
final class ScalarSubqueryTest extends TestCase
{
    public function testDeriveScalarHasTheTypeOfTheOneColumnAndCanBeNull(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $subquery = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse("SELECT (SELECT 'x')")->find('expr')[0]);
        $derivation = new Derivation($platform->context($profile, null, [], true));
        $fact = $derivation->scalar($subquery, $derivation->environment());

        self::assertInstanceOf(ScalarSubquery::class, $subquery);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame(['VARCHAR', Nullability::Nullable], [$fact->type->descriptor->name(), $fact->nullability]);
    }

    public function testDeriveScalarIsARowForSeveralColumns(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $subquery = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT (SELECT 1, 2)')->find('expr')[0]);
        $derivation = new Derivation($platform->context($profile, null, [], true));
        $fact = $derivation->scalar($subquery, $derivation->environment());

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertEquals(new Tuple(2), $fact->type->descriptor);
    }

    public function testRenderWritesTheQueryInParentheses(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $subquery = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT (select 1)')->find('expr')[0]);
        $out = new Output($platform->codec($profile));
        $subquery->render($out);

        self::assertSame('(SELECT 1)', (new Lexical())->join($out->pieces()));
    }
}
