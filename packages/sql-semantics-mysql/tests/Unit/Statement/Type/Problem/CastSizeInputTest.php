<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit;

#[CoversClass(CastSizeInput::class)]
#[Small]
final class CastSizeInputTest extends TestCase
{
    public function testMessageDescribesTheBoundWithoutInputText(): void
    {
        $bound = new InvalidTypeSize(TypeLimit::Precision, '', 66, 65);
        self::assertSame($bound->message(), (new CastSizeInput($bound, 18, 22))->message());
    }
}
