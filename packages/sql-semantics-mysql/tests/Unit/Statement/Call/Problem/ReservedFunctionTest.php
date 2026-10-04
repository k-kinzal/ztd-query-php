<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ReservedFunction::class)]
#[Small]
final class ReservedFunctionTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame("Access to native function 'internal_table_rows' is rejected", (new ReservedFunction(new Name('internal_table_rows')))->message());
    }
}
