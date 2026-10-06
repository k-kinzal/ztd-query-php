<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\ConditionDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ConditionDiagnostics::class)]
#[Medium]
final class ConditionDiagnosticsTest extends TestCase
{
    public function testRenderWritesTheNumberAndTheItems(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ConditionDiagnostics(new NumberLiteral('2'), [
            new InformationItem(new UserVariable(new Name('m')), ConditionItemName::MessageText),
            new InformationItem(new Name('e'), ConditionItemName::MysqlErrno),
        ]))->render($out);

        self::assertSame('CONDITION 2 @m = MESSAGE_TEXT, e = MYSQL_ERRNO', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheAnalyzedInformation(): void
    {
        $get = (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('GET DIAGNOSTICS CONDITION 2 @m = MESSAGE_TEXT, @c = CURSOR_NAME');
        $statement = $get->statement;
        self::assertInstanceOf(GetDiagnostics::class, $statement);
        self::assertInstanceOf(ConditionDiagnostics::class, $statement->information);

        self::assertEquals(new NumberLiteral('2'), $statement->information->number);
        self::assertSame(
            [ConditionItemName::MessageText, ConditionItemName::CursorName],
            array_map(static fn (InformationItem $item): ConditionItemName|StatementItemName => $item->item, $statement->information->items),
        );
        self::assertSame('GET DIAGNOSTICS CONDITION 2 @m = MESSAGE_TEXT, @c = CURSOR_NAME', $get->toString());
    }

    public function testRefusesAnEmptyList(): void
    {
        $this->expectExceptionMessage('GET DIAGNOSTICS reads at least one item.');

        new ConditionDiagnostics(new NumberLiteral('1'), []);
    }

    public function testRefusesAStatementInformationItem(): void
    {
        $this->expectExceptionMessage('Condition information holds condition information items.');

        new ConditionDiagnostics(new NumberLiteral('1'), [
            new InformationItem(new UserVariable(new Name('m')), ConditionItemName::MessageText),
            new InformationItem(new UserVariable(new Name('n')), StatementItemName::Number),
        ]);
    }
}
