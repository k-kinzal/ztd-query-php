<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

#[CoversClass(ParameterStyle::class)]
#[Medium]
final class ParameterStyleTest extends TestCase
{
    public function testTheStylesAreNativeAndNamed(): void
    {
        self::assertSame('Native', ParameterStyle::Native->value);
        self::assertSame('Named', ParameterStyle::Named->value);
        self::assertCount(2, ParameterStyle::cases());
    }

    public function testTheSelectedStyleIsPartOfTheProfile(): void
    {
        $semantics = new Semantics(Dialect::Sqlite, null, null, ParameterStyle::Named);

        self::assertSame(ParameterStyle::Named, $semantics->profile()->parameters);
        self::assertFalse($semantics->profile()->compatibleWith((new Semantics(Dialect::Sqlite))->profile()));
    }
}
