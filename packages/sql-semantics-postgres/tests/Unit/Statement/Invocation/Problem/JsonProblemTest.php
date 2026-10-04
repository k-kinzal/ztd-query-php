<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblemKind;

#[CoversClass(JsonProblem::class)]
#[Small]
final class JsonProblemTest extends TestCase
{
    public function testMessageNamesTheSubjects(): void
    {
        self::assertSame('invalid ON EMPTY behavior for column "c"', (new JsonProblem(JsonProblemKind::InvalidColumnBehavior, ['ON EMPTY', 'c']))->message());
    }

    public function testRejectsAMissingSubject(): void
    {
        $this->expectExceptionMessage('A JSON problem names exactly the subjects its message has.');
        new JsonProblem(JsonProblemKind::InvalidBehavior);
    }
}
