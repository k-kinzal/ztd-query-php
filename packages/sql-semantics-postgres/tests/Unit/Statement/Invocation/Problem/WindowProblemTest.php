<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblemKind;

#[CoversClass(WindowProblem::class)]
#[Small]
final class WindowProblemTest extends TestCase
{
    public function testMessageIsTheServerMessage(): void
    {
        self::assertSame('GROUPS mode requires an ORDER BY clause', (new WindowProblem(WindowProblemKind::GroupsWithoutOrder))->message());
    }
}
