<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;

#[CoversClass(RoutineProblem::class)]
#[Small]
final class RoutineProblemTest extends TestCase
{
    public function testMessageInsertsTheSubject(): void
    {
        self::assertSame('parameter name "a" used more than once', (new RoutineProblem(RoutineProblemKind::DuplicateParameter, 'a'))->message());
    }

    public function testMessageWithoutSubject(): void
    {
        self::assertSame('missing argument', (new RoutineProblem(RoutineProblemKind::MissingOperatorArgument))->message());
    }
}
