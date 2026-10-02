<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\Rendering as R;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(R\SqliteCaseArmLayout::class)]
#[Small]
final class SqliteCaseArmLayoutTest extends TestCase
{
    public function testWriteBuildsTheActualTwoOperandsWithCheckedKeywords(): void
    {
        $layout = new R\SqliteCaseArmLayout('when', 'ThEn', afterWhen: '/*test*/', afterThen: '/*result*/');
        self::assertSame('when/*test*/1 ThEn/*result*/2', $layout->write('1', '2'));
    }

    public function testAppendSeparatesAFirstOrSubsequentBranch(): void
    {
        $layout = new R\SqliteCaseArmLayout(before: '');
        self::assertSame('CASE WHEN 1 THEN 2', $layout->append('CASE', $layout->write('1', '2')));
    }

    public function testRejectsAnUnterminatedCommentBeforeAResult(): void
    {
        $this->expectException(InvalidConstruction::class);
        new R\SqliteCaseArmLayout(afterThen: '-- hidden');
    }

    public function testRejectsAnOperandInAKeyword(): void
    {
        $this->expectException(InvalidConstruction::class);
        new R\SqliteCaseArmLayout('WHEN 1');
    }
}
