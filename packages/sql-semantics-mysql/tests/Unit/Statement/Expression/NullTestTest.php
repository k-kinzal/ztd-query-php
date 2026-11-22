<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(NullTest::class)]
#[Medium]
final class NullTestTest extends TestCase
{
    public function testDeriveScalarIsNeverNull(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertSame(Nullability::NotNull, $derivation->scalar(new NullTest(new NullLiteral()), $derivation->environment())->nullability);
    }

    public function testDeriveScalarReportsARowOperand(): void
    {
        $operation = (new Inspection())->diagnose('SELECT (1, 2) IS NULL');

        self::assertSame(['Operand should contain 1 column(s), not 2.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testRenderChainsTestsToTheLeft(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new NullTest(new NullTest(new NumberLiteral('1')), true))->render($out);

        self::assertSame('1 IS NULL IS NOT NULL', (new Lexical())->join($out->pieces()));
    }

    public function testATruthTestOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of a NULL test needs a grouping to keep its place.');

        new NullTest(new TruthTest(new NumberLiteral('1'), Truth::True));
    }
}
