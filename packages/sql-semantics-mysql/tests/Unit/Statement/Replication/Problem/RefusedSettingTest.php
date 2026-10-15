<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

#[CoversClass(RefusedSetting::class)]
#[Small]
final class RefusedSettingTest extends TestCase
{
    public function testMessageDescribesTheCheck(): void
    {
        self::assertSame(ReplicationError::WildPattern->value, (new RefusedSetting(ReplicationError::WildPattern))->message());
    }

    public function testValueRequiresTheRefusedLogIndex(): void
    {
        $this->expectExceptionMessage('A refused binary log index retains its numeric operand.');
        new RefusedSetting(ReplicationError::FileNumberOutOfRange);
    }

    public function testValueRejectsAnUnrelatedNumericOperand(): void
    {
        $this->expectExceptionMessage('A refused binary log index retains its numeric operand.');
        new RefusedSetting(ReplicationError::WildPattern, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('0'));
    }
    public function testMessageRetainsTheLineFeedOperandWithoutAStatement(): void
    {
        $text = new \SqlSemantics\Platform\MySql\Statement\Literal\Text("a\nb");
        $problem = new RefusedSetting(ReplicationError::LineFeed, text: $text);

        self::assertSame($text, $problem->text);
        self::assertSame(ReplicationError::LineFeed->value, $problem->message());
    }

}
