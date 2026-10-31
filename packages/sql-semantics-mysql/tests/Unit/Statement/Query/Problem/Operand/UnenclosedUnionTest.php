<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem\Operand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion;

#[CoversClass(UnenclosedUnion::class)]
#[Small]
final class UnenclosedUnionTest extends TestCase
{
    public function testMessageNeedsNeitherSourceTextNorAnInputPosition(): void
    {
        self::assertSame('A UNION starting with parentheses requires every operand to be enclosed in MySQL 5.6.', (new UnenclosedUnion())->message());
        self::assertNull((new UnenclosedUnion())->offset);
    }

}
