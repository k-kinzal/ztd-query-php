<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\ArrayItems;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(ArrayItems::class)]
#[Small]
final class ArrayItemsTest extends TestCase
{
    public function testRenderWritesNestedLevels(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ArrayItems([], [new ArrayItems([new NullLiteral()]), new ArrayItems()]))->render($out);
        self::assertSame('[[NULL], []]', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsValuesAndSubArraysTogether(): void
    {
        $this->expectExceptionMessage('An array level holds values or sub-arrays, not both.');
        new ArrayItems([new NullLiteral()], [new ArrayItems()]);
    }
}
