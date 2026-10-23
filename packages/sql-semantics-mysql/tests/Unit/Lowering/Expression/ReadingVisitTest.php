<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Lowering\Expression\ReadingVisit;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(ReadingVisit::class)]
#[Small]
final class ReadingVisitTest extends TestCase
{
    public function testVisitKeepsInputContextForPostorderAssignmentReading(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('SELECT @a := 1');
        $visit = new ReadingVisit($node, true, true);
        self::assertSame($node, $visit->node);
        self::assertTrue($visit->view);
        self::assertTrue($visit->after);
    }
}
