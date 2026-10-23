<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable;

#[CoversClass(UnstructuredVariable::class)]
#[Small]
final class UnstructuredVariableTest extends TestCase
{
    public function testMessageNamesTheVariableAsTheServerDoes(): void
    {
        self::assertSame("Variable 'sql_mode' is not a variable component (can't be used as XXXX.variable_name)", (new UnstructuredVariable('sql_mode'))->message());
    }
}
