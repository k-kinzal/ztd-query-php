<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Spelling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(Layout::class)]
#[Small]
final class LayoutTest extends TestCase
{
    public function testTextJoinsTheTokensWithTheirGapsButNotTheFirstGap(): void
    {
        $layout = new Layout([new Spelled('  ', 'a'), new Spelled(' ', '+'), new Spelled('/**/', '1')]);

        self::assertSame('a +/**/1', $layout->text());
    }

    public function testLayoutRefusesAnEmptyTokenList(): void
    {
        $this->expectExceptionMessage('A layout spells at least one token.');

        new Layout([]);
    }

    public function testTrailKeepsTheTriviaAfterTheLastTokenOutsideTheText(): void
    {
        $layout = new Layout([new Spelled('', '1')], ' /* c */ ');

        self::assertSame(' /* c */ ', $layout->trail);
        self::assertSame('1', $layout->text());
    }
}
