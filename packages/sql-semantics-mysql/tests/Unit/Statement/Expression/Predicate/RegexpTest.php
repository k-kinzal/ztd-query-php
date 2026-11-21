<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

#[CoversClass(Regexp::class)]
#[Medium]
final class RegexpTest extends TestCase
{
    public function testDeriveScalarIsNullWhenThePatternCanBe(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertSame(Nullability::Nullable, $derivation->scalar(new Regexp(new StringLiteral(['a']), new NullLiteral()), $derivation->environment())->nullability);
    }

    public function testRenderWritesTheKeyword(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Regexp(new StringLiteral(['a']), new StringLiteral(['^a']), true))->render($out);

        self::assertSame("'a' NOT REGEXP '^a'", (new Lexical())->join($out->pieces()));
    }

    public function testAPredicatePatternIsRejected(): void
    {
        $this->expectExceptionMessage('The pattern of REGEXP needs a grouping to keep its place.');

        new Regexp(new StringLiteral(['a']), new InList(new NumberLiteral('1'), [new NumberLiteral('2')]));
    }
}
