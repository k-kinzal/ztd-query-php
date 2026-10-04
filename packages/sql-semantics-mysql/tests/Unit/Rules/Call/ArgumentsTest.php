<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Arguments::class)]
#[Small]
final class ArgumentsTest extends TestCase
{
    public function testOneDerivesASingleValue(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $fact = (new Arguments())->one(new NumberLiteral('1'), $derivation, $derivation->environment());

        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
