<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RelationReference::class)]
#[Small]
final class RelationReferenceTest extends TestCase
{
    public function testRenderWritesOnlyBeforeTheName(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RelationReference(new QualifiedName(new Name('t'), new Name('s')), true))->render($out);
        self::assertSame('ONLY s.t', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesAPlainNameWithoutOnly(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RelationReference(new QualifiedName(new Name('User'))))->render($out);
        self::assertSame('"User"', (new Lexical())->join($out->pieces()));
    }
}
