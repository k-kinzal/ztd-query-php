<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Lexical;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\MatchName;

#[CoversClass(MatchName::class)]
#[Small]
final class MatchNameTest extends TestCase
{
    public function testRenderWritesTheKeywordAndTheName(): void
    {
        $out = new Output(new Codec());
        (new MatchName(new Name('partial')))->render($out);

        self::assertSame('MATCH partial', (new Lexical())->join($out->pieces()));
    }
}
