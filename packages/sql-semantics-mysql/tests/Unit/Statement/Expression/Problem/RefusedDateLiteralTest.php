<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedDateLiteral;

#[CoversClass(RefusedDateLiteral::class)]
#[Small]
final class RefusedDateLiteralTest extends TestCase
{
    public function testMessageRetainsTheInvalidDateOperand(): void
    {
        $problem = new RefusedDateLiteral('bad');

        self::assertSame(['Incorrect DATE value.', 'bad'], [$problem->message(), $problem->text]);
    }
}
