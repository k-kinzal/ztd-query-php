<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling::class)]
#[Small]
final class AliasSpellingTest extends TestCase
{
    public function testWriteWritesAsTheAliasAndColumns(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling())->write($out, new \SqlSemantics\Statement\Identifier\Name('x'), [new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\Name('B')]);
        self::assertSame('AS x (a, "B")', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testNamesWritesNothingForNoName(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling())->names($out, []);
        self::assertSame([], $out->pieces());
    }
}
