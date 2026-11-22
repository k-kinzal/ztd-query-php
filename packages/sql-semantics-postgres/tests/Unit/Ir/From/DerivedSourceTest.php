<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\DerivedSource;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Select;

#[CoversClass(DerivedSource::class)]
#[Small]
final class DerivedSourceTest extends TestCase
{
    public function testRetainsTheCheckedDefinition(): void
    {
        $definition = new Select([new ResultColumn(new IntegerLiteral('7'), new Identifier('answer'))]);

        self::assertSame($definition, (new DerivedSource($definition))->definition);
    }

    public function testRejectsAnUnsupportedField(): void
    {
        $this->expectExceptionMessage('A derived join field requires a signed literal or a signed column.');
        new DerivedSource(new Select([new ResultColumn(new NullLiteral(), new Identifier('answer'))]));
    }
}
