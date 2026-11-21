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
        self::assertSame('schema', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionSchema(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ext')))->option());
    }

    public function testRenderWritesSchema(): void
    {
        $out = new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionSchema(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('Ext')))->render($out);
        self::assertSame('SCHEMA "Ext"', (new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical())->join($out->pieces()));
    }
}
