<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\PrefixFrame;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(PrefixFrame::class)]
#[Small]
final class PrefixFrameTest extends TestCase
{
    public function testBodyKeepsDeclarationsInTheirEnclosingBlocks(): void
    {
        $outer = new PrefixFrame();
        $outer->declarations[] = new ConditionDeclaration(new Name('c'), new SqlState(new Text('45000')));
        $inner = new PrefixFrame($outer);
        $inner->statements[] = new Signal(new ConditionName(new Name('c')));

        self::assertEquals(new Block($outer->declarations, [new Block([], $inner->statements)]), $inner->body());
        self::assertSame([], $outer->statements);
    }
}
