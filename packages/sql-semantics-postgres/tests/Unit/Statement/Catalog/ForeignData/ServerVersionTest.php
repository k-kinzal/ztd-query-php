<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ServerVersion::class)]
#[Medium]
final class ServerVersionTest extends TestCase
{
    public function testRenderWritesNull(): void
    {
        $out = new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ServerVersion())->render($out);
        self::assertSame('VERSION NULL', (new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheVersion(): void
    {
        self::assertSame('ALTER SERVER s VERSION \'2\'', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ALTER SERVER s VERSION \'2\'')->toString());
    }
}
