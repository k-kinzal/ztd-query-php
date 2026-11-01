<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UndeclaredVariable::class)]
#[Small]
final class UndeclaredVariableTest extends TestCase
{
    public function testMessageNamesTheVariable(): void
    {
        self::assertSame('Undeclared variable: n', (new UndeclaredVariable(new Name('n')))->message());
    }
}
