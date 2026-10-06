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
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ResultTable;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ResultTable::class)]
#[Small]
final class ResultTableTest extends TestCase
{
    public function testDeriveClauseDerivesEveryColumn(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new ResultTable([new ResultColumn(new Name('a'), new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))))]))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTableAndColumns(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ResultTable([new ResultColumn(new Name('a'), new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))), new ResultColumn(new Name('b'), new TypeName(new NamedDesignation(new DottedName([new Name('text')]))))]))->render($out);
        self::assertSame('TABLE (a int4, b text)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnEmptyTable(): void
    {
        $this->expectExceptionMessage('A result table has at least one column.');
        new ResultTable([]);
    }
}
