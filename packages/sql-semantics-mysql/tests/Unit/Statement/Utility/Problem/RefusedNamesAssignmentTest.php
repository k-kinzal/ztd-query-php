<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment;

#[CoversClass(RefusedNamesAssignment::class)]
#[Small]
final class RefusedNamesAssignmentTest extends TestCase
{
    public function testMessageRetainsOnlyPositionsAndTheScopeFact(): void
    {
        $problem = new RefusedNamesAssignment(10, 13);

        self::assertSame(['offset' => 10, 'end' => 13, 'shadowed' => false], get_object_vars($problem));
        self::assertSame('SET NAMES takes a character set name, not an expression', $problem->message());
        self::assertSame('Variable \'names\' must be quoted with `...`, or renamed', (new RefusedNamesAssignment(10, 13, true))->message());
    }
}
