<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClause;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(ValuesClause::class)]
#[Medium]
final class ValuesClauseTest extends TestCase
{
    public function testRejectsNoRows(): void
    {
        $this->expectExceptionMessage('VALUES contains at least one row.');
        new ValuesClause([]);
    }

    public function testRejectsUnequalWidths(): void
    {
        $this->expectExceptionMessage('VALUES rows have equal widths.');
        new ValuesClause([[new StringLiteral(['42'])], []]);
    }

    public function testRenderKeepsRowsAndTheChosenKeyword(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ValuesClause([[new StringLiteral(['42'])], [new StringLiteral(['7'])]], keyword: ValuesKeyword::Value))->render($out);
        self::assertSame("VALUE ('42'), ('7')", (new Lexical())->join($out->pieces()));
    }
}
