<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

#[CoversClass(CurrentTimestamp::class)]
#[Small]
final class CurrentTimestampTest extends TestCase
{
    public function testDecimalsPreservesADeclarationOperandUntilItsEffectiveUse(): void
    {
        self::assertSame([0, 6, 9], [(new CurrentTimestamp())->decimals(), (new CurrentTimestamp(new Numeral('262')))->decimals(), (new CurrentTimestamp(new Numeral('9')))->decimals()]);
    }

    public function testRenderPreservesWrittenParenthesesAndPrecision(): void
    {
        $out = new \SqlSemantics\Rendering\Output((new \SqlSemantics\Platform\MySql\Platform())->codec((new Semantics(Dialect::MySql))->profile()));
        (new CurrentTimestamp(new Numeral('0009')))->render($out);
        (new CurrentTimestamp(null, OptionalWords::Omitted))->render($out);

        self::assertSame('CURRENT_TIMESTAMP(0009) CURRENT_TIMESTAMP', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testDeclarationDomainPermitsAnOverriddenAttribute(): void
    {
        $superseded = new DefaultLiteral(new CurrentTimestamp(new Numeral('9')));
        $effective = new DefaultLiteral(new CurrentTimestamp());
        $column = new OrdinaryColumn(new Temporal(TemporalKind::DateTime), [$superseded, $effective]);

        self::assertSame([$superseded, $effective], $column->columnAttributes());
    }

    public function testDeclarationDomainRequiresACompatibleEffectiveDefault(): void
    {
        $value = new CurrentTimestamp(new Numeral('9'));
        $this->expectExceptionMessage('Column attributes must fit the declared type and temporal precision.');
        new OrdinaryColumn(new Temporal(TemporalKind::DateTime), [new DefaultLiteral($value)]);
    }
}
