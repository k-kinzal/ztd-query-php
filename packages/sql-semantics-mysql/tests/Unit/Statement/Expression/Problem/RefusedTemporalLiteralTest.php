<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral;

#[CoversClass(RefusedTemporalLiteral::class)]
#[Small]
final class RefusedTemporalLiteralTest extends TestCase
{
    public function testMessageRetainsTheInvalidDateOperand(): void
    {
        $problem = new RefusedTemporalLiteral('bad');

        self::assertSame(['Incorrect DATE value.', 'bad'], [$problem->message(), $problem->text]);
    }
    public function testMessageNamesTheTemporalForm(): void
    {
        $problem = new RefusedTemporalLiteral('bad', \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Time);

        self::assertSame(['Incorrect TIME value.', 'bad'], [$problem->message(), $problem->text]);
    }
}
