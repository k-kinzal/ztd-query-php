<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionSchema::class)]
#[Small]
final class ExtensionSchemaTest extends TestCase
{
    public function testOptionIsSchema(): void
    {
        self::assertSame('schema', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionSchema(new \SqlSemantics\Statement\Identifier\Name('ext')))->option());
    }

    public function testRenderWritesSchema(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionSchema(new \SqlSemantics\Statement\Identifier\Name('Ext')))->render($out);
        self::assertSame('SCHEMA "Ext"', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }
}
