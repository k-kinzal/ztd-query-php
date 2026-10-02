<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GenericOption::class)]
#[Small]
final class GenericOptionTest extends TestCase
{
    public function testRenderWritesTheNameAndTheValue(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new GenericOption(new Name('Host'), new StringConstant('db1')))->render($out);
        self::assertSame('"Host" \'db1\'', (new Lexical())->join($out->pieces()));
    }
}
