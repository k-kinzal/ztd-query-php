<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableRule;

#[CoversClass(VariableMisuse::class)]
#[Small]
final class VariableMisuseTest extends TestCase
{
    public function testMessageIsTheServerMessageOfEachRule(): void
    {
        $messages = array_map(static fn (VariableRule $rule): string => (new VariableMisuse($rule, 'v'))->message(), VariableRule::cases());

        self::assertSame([
            "Variable 'v' is a SESSION variable",
            "Variable 'v' is a GLOBAL variable",
            "Variable 'v' is a read only variable",
            "Variable 'v' is a SESSION variable and can't be used with SET GLOBAL",
            "Variable 'v' is a GLOBAL variable and should be set with SET GLOBAL",
            "SESSION variable 'v' is read-only. Use SET GLOBAL to assign the value",
        ], $messages);
    }
}
