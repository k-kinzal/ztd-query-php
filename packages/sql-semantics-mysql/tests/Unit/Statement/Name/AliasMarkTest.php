<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(AliasMark::class)]
#[Small]
final class AliasMarkTest extends TestCase
{
    public function testWriteWritesWhatPrecedesTheAlias(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql5744));
        AliasMark::As->write($out);
        AliasMark::Bare->write($out);
        AliasMark::Equals->write($out);

        self::assertSame('AS =', (new Lexical())->join($out->pieces()));
    }
}
