<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DeferredCallArgument::class)]
#[Small]
final class DeferredCallArgumentTest extends TestCase
{
    public function testRenderWritesTheAlias(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new DeferredCallArgument(new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new ColumnUse(new Name('a')), new ColumnUse(new Name('b'))]), new Name('total')))->render($out);

        self::assertSame('(a, b) AS total', (new Lexical())->join($out->pieces()));
    }
}
