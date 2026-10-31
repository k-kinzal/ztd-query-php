<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict;

#[CoversClass(CharsetConflict::class)]
#[Small]
final class CharsetConflictTest extends TestCase
{
    public function testMessageDescribesTheRetainedProblem(): void
    {
        $problem = new CharsetConflict(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('latin1'), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));

        self::assertSame('utf8mb4', $problem->later->name);
        self::assertSame("Conflicting declarations: 'CHARACTER SET latin1' and 'CHARACTER SET utf8mb4'", $problem->message());
    }
}
