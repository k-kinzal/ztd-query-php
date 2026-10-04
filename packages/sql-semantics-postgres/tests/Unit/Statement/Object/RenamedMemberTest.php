<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedMember;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedPart;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RenamedMember::class)]
#[Small]
final class RenamedMemberTest extends TestCase
{
    public function testRenderWritesKeywordAndName(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RenamedMember(RenamedPart::Constraint, new Name('c')))->render($out);
        self::assertSame('CONSTRAINT c', (new Lexical())->join($out->pieces()));
    }
}
