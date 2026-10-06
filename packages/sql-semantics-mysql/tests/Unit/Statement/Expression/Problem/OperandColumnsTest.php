<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;

#[CoversClass(OperandColumns::class)]
#[Small]
final class OperandColumnsTest extends TestCase
{
    public function testMessageNamesBothWidths(): void
    {
        self::assertSame('Operand should contain 2 column(s), not 3.', (new OperandColumns(2, 3))->message());
    }

    public function testEqualWidthsAreRejected(): void
    {
        $this->expectExceptionMessage('An operand column problem names two different column counts.');

        new OperandColumns(1, 1);
    }
}
