<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Statement\Schema\ModuleArgument;
use SqlSemantics\Rendering\Output;

#[CoversClass(ModuleArgument::class)]
#[Small]
final class ModuleArgumentTest extends TestCase
{
    public function testPassedIsFalseForAnEmptyArgument(): void
    {
        self::assertTrue((new ModuleArgument('content=docs'))->passed());
        self::assertFalse((new ModuleArgument(''))->passed());
    }

    public function testRenderWritesTheTextUnchanged(): void
    {
        $out = new Output(new Codec());
        (new ModuleArgument("tokenize  =  'porter ascii'"))->render($out);

        self::assertSame("tokenize  =  'porter ascii'", $out->pieces()[0]->text);
    }

    public function testRenderWritesNothingForAnEmptyArgument(): void
    {
        $out = new Output(new Codec());
        (new ModuleArgument(''))->render($out);

        self::assertSame([], $out->pieces());
    }
}
