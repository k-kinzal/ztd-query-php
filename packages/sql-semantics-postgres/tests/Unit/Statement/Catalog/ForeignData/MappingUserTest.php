<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\MappingUser::class)]
#[Small]
final class MappingUserTest extends TestCase
{
    public function testRenderWritesUser(): void
    {
        $out = new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172));
        \SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\MappingUser::User->render($out);
        self::assertSame('USER', (new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical())->join($out->pieces()));
    }
}
