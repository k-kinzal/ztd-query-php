<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\ResultColumn;

#[CoversClass(ResultColumn::class)]
#[Small]
final class ResultColumnTest extends TestCase
{
    public function testPairsValueWithOutputName(): void
    {
        $value = new NullLiteral();
        $name = new Identifier('');
        $column = new ResultColumn($value, $name);

        self::assertSame($name, $column->name);
        self::assertSame($value, $column->expression);
    }
}
