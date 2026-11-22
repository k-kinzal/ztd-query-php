<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StatementDiagnostics::class)]
#[Medium]
final class StatementDiagnosticsTest extends TestCase
{
    public function testRenderWritesTheItems(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql5744));
        (new StatementDiagnostics([
            new InformationItem(new Name('n'), StatementItemName::Number),
            new InformationItem(new UserVariable(new Name('r')), StatementItemName::RowCount),
        ]))->render($out);

        self::assertSame('n = NUMBER, @r = ROW_COUNT', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheAnalyzedInformation(): void
    {
        $get = (new Inspection('mysql-5.6.51'))->inspect('get diagnostics @r = row_count, @n = number');
        $statement = $get->statement;
        self::assertInstanceOf(GetDiagnostics::class, $statement);
        self::assertInstanceOf(StatementDiagnostics::class, $statement->information);

        self::assertSame(
            [StatementItemName::RowCount, StatementItemName::Number],
            array_map(static fn (InformationItem $item): ConditionItemName|StatementItemName => $item->item, $statement->information->items),
        );
        self::assertSame('GET DIAGNOSTICS @r = ROW_COUNT, @n = NUMBER', $get->toString());
    }

    public function testRefusesAnEmptyList(): void
    {
        $this->expectExceptionMessage('GET DIAGNOSTICS reads at least one item.');

        new StatementDiagnostics([]);
    }

    public function testRefusesAConditionInformationItem(): void
    {
        $this->expectExceptionMessage('Statement information holds statement information items.');

        new StatementDiagnostics([new InformationItem(new UserVariable(new Name('m')), ConditionItemName::MessageText)]);
    }
}
