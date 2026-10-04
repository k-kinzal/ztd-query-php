<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CallArgument::class)]
#[Small]
final class CallArgumentTest extends TestCase
{
    public function testRenderWritesTheAlias(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new CallArgument(new ColumnUse(new Name('a')), new Name('total')))->render($out);

        self::assertSame('a AS total', (new Lexical())->join($out->pieces()));
    }
}
