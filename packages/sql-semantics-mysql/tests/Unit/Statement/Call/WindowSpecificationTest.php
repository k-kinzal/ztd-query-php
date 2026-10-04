<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Statement\Type\Nullability;

#[CoversNothing]
#[Small]
final class WindowSpecificationTest extends TestCase
{
    public function testDeriveWindowDerivesTheExpressions(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $partition = new NumberLiteral('1');
        (new WindowSpec(null, [new OrderItem($partition)]))->deriveWindow($derivation, $derivation->environment());

        self::assertSame(Nullability::NotNull, $derivation->facts()->scalar($partition)->nullability);
        self::assertContains(WindowSpecification::class, class_implements(WindowSpec::class));
    }
}
