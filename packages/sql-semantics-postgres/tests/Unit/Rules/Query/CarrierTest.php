<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Carrier;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Rendering\Output;

#[CoversClass(Carrier::class)]
#[Small]
final class CarrierTest extends TestCase
{
    public function testDeriveRelationAnswersARowWithoutColumns(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $carrier = new Carrier(new QueryExpression(null, new ValuesList([new ValuesRow([new Constant(new IntegerConstant('1'))])]), new SelectOptions(readOnly: true)));
        $shape = $carrier->deriveRelation($derivation, $derivation->environment())->shape;
        self::assertSame([[], true], [$shape->slots, $shape->complete()]);
    }

    public function testRenderWritesNothing(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Carrier(new QueryExpression(null, new ValuesList([new ValuesRow([new Constant(new IntegerConstant('1'))])]), new SelectOptions(readOnly: true))))->render($out);
        self::assertSame([], $out->pieces());
    }
}
