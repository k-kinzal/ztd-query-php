<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblemKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ArgumentProblem::class)]
#[Small]
final class ArgumentProblemTest extends TestCase
{
    public function testMessageNamesTheArgument(): void
    {
        self::assertSame('argument name "b" used more than once', (new ArgumentProblem(ArgumentProblemKind::RepeatedName, new Name('b')))->message());
    }
}
