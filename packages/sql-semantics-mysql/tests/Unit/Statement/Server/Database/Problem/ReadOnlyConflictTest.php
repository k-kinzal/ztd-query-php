<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\ReadOnlyConflict;

#[CoversClass(ReadOnlyConflict::class)]
#[Small]
final class ReadOnlyConflictTest extends TestCase
{
    public function testMessageDescribesTheRetainedProblem(): void
    {
        $problem = new ReadOnlyConflict();

        self::assertSame("Conflicting declarations: 'READ ONLY=0' and 'READ ONLY=1'", $problem->message());
    }
}
