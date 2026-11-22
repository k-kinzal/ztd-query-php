<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateComposite::class)]
#[Medium]
final class CreateCompositeTest extends TestCase
{
    public function testRenderWritesTheAttributes(): void
    {
        $out = new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateComposite(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('pair')]), []))->render($out);
        self::assertSame('CREATE TYPE pair AS ()', (new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical())->join($out->pieces()));
    }

    public function testDeriveStatementReportsARepeatedAttribute(): void
    {
        self::assertSame('column "a" specified more than once', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE TYPE pair AS (a int4, b text, a int8)')->facts->diagnostics[0]->message());
    }
}
