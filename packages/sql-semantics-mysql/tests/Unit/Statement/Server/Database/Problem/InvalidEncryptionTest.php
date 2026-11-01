<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption;

#[CoversClass(InvalidEncryption::class)]
#[Small]
final class InvalidEncryptionTest extends TestCase
{
    public function testMessageDescribesTheRetainedProblem(): void
    {
        $problem = new InvalidEncryption(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('bad'));

        self::assertSame('bad', $problem->value->value);
        self::assertSame("Incorrect argument (should be Y or N) value: 'bad'", $problem->message());
    }
}
