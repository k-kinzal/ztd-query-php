<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Argument;

#[CoversClass(Argument::class)]
#[Small]
final class ArgumentTest extends TestCase
{
    public function testValueAndNameAreTheContractOfACallArgument(): void
    {
        self::assertSame([true, true], [method_exists(Argument::class, 'value'), method_exists(Argument::class, 'name')]);
    }
}
