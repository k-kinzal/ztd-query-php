<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute;

#[CoversClass(RefusedJsonAttribute::class)]
#[Small]
final class RefusedJsonAttributeTest extends TestCase
{
    public function testMessageDescribesTheDecodedOperandBoundary(): void
    {
        $failure = new RefusedJsonAttribute(new Text('1.'), 'Miss fraction part in number.', 2);
        self::assertSame('Invalid JSON engine attribute at byte 2: Miss fraction part in number.', $failure->message());
    }

    public function testInvalidPositionCannotEscapeTheOperand(): void
    {
        $this->expectExceptionMessage('A JSON condition identifies a byte boundary of its operand.');
        new RefusedJsonAttribute(new Text('bad'), 'Invalid value.', 4);
    }
}
