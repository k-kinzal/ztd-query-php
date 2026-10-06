<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

#[CoversNothing]
#[Small]
final class ColumnSpecificationTest extends TestCase
{
    public function testImplementationsDeclareTheInterface(): void
    {
        self::assertContains(ColumnSpecification::class, class_implements(OrdinaryColumn::class));
    }

    public function testDataTypeIsDeclaredByTheInterface(): void
    {
        self::assertSame('INT', (new OrdinaryColumn(new Integral(IntegralKind::Int)))->dataType()->name());
    }

    public function testColumnAttributesIsDeclaredByTheInterface(): void
    {
        self::assertSame([], (new OrdinaryColumn(new Integral(IntegralKind::Int)))->columnAttributes());
    }

    public function testDeriveSpecificationIsDeclaredByTheInterface(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $literal = new BooleanLiteral(true);
        $specification = new OrdinaryColumn(new Integral(IntegralKind::Int), [new DefaultLiteral($literal)]);
        $specification->deriveSpecification($derivation, $derivation->environment());

        self::assertTrue($derivation->facts()->covers($literal));
    }
}
