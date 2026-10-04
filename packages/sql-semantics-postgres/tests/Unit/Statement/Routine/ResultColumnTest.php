<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ResultColumn::class)]
#[Small]
final class ResultColumnTest extends TestCase
{
    public function testDeriveClauseRecordsNothingForAPlainType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new ResultColumn(new Name('a'), new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesNameAndType(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ResultColumn(new Name('Id'), new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))))->render($out);
        self::assertSame('"Id" int4', (new Lexical())->join($out->pieces()));
    }
}
