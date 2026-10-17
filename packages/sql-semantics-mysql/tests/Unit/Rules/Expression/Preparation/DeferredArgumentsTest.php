<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Preparation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(DeferredArguments::class)]
#[Small]
final class DeferredArgumentsTest extends TestCase
{
    public function testProblemSkipsScalarDependenciesAndKeepsTheFirstRowWidth(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2'), new NumberLiteral('3')]);
        $problem = (new DeferredArguments())->problem([new NumberLiteral('0'), $row]);
        self::assertEquals(new OperandColumns(1, 3), $problem);
    }
}
