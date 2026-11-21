<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Rendering\LeadingDot;

#[CoversClass(LeadingDot::class)]
#[Small]
final class LeadingDotTest extends TestCase
{
    public function testWriteSeparatesTheDotFromAPrecedingWordAndJoinsTheNameToIt(): void
    {
        $out = (new Output(new Codec(GrammarRelease::MySql5744)))->keyword('FROM');
        (new LeadingDot())->write($out);
        $out->name(new Name('t'));

        self::assertSame('FROM .t', (new Lexical())->join($out->pieces()));
    }
}
