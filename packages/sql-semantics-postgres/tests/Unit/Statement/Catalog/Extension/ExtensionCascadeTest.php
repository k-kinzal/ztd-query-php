<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionCascade::class)]
#[Small]
final class ExtensionCascadeTest extends TestCase
{
    public function testOptionIsCascade(): void
    {
        self::assertSame('cascade', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionCascade::Cascade->option());
    }

    public function testRenderWritesTheKeyword(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionCascade::Cascade->render($out);
        self::assertSame('CASCADE', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }
}
