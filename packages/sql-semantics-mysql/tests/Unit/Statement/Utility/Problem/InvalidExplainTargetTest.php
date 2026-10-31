<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(InvalidExplainTarget::class)]
#[Small]
final class InvalidExplainTargetTest extends TestCase
{
    public function testMessagePreservesTheCompleteDecodedTarget(): void
    {
        $target = new UserVariable(new Name(str_repeat('é', 65)));
        $problem = new InvalidExplainTarget($target);
        self::assertSame($target, $problem->target);
        self::assertSame("User variable name '" . str_repeat('é', 65) . "' is illegal", $problem->message());
    }

    public function testRefusesAValidTarget(): void
    {
        $this->expectExceptionMessage('A refused EXPLAIN target has an empty or overlong name.');
        new InvalidExplainTarget(new UserVariable(new Name('p')));
    }
}
