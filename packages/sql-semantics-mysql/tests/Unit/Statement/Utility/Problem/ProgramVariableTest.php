<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\ProgramVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ProgramVariable::class)]
#[Small]
final class ProgramVariableTest extends TestCase
{
    public function testDescribeNamesTheName(): void
    {
        self::assertSame('the variable declarations of the enclosing stored program, which decide what y denotes', (new ProgramVariable(new Name('y')))->describe());
    }
}
