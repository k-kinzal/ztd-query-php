<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionAction;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AlteredOption::class)]
#[Small]
final class AlteredOptionTest extends TestCase
{
    public function testRenderWritesTheActionNameAndValue(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new AlteredOption(OptionAction::Set, new Name('host'), new StringConstant('db2')))->render($out);
        self::assertSame("SET host 'db2'", (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new AlteredOption(OptionAction::Drop, new Name('host')))->render($second);
        self::assertSame('DROP host', (new Lexical())->join($second->pieces()));
    }

    public function testRejectsADroppedOptionWithAValue(): void
    {
        $this->expectExceptionMessage('An option has a value exactly when it is added or set.');
        new AlteredOption(OptionAction::Drop, new Name('host'), new StringConstant('x'));
    }
}
