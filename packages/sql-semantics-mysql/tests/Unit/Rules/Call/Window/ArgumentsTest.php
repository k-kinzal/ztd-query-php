<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Arguments;
use SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Arguments::class)]
#[Small]
final class ArgumentsTest extends TestCase
{
    public function testCheckedRetainsDistinctCountAndValueRoles(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $offset = new RoutineVariable(new Name('n'));
        $arguments = [$row, $offset];

        self::assertSame($arguments, (new Arguments())->checked(WindowFunctionKind::Lead, $arguments, null, null));
        self::assertSame([], (new Arguments())->checked(WindowFunctionKind::RowNumber, [], null, null));
    }
}
