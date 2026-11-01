<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Restart;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Fact\Notice;

#[CoversClass(StatementSemantics::class)]
#[Medium]
final class StatementSemanticsTest extends TestCase
{
    public function testWarningInstructionRetainsItsStoredInstruction(): void
    {
        $warning = new Deprecation(Deprecated::BinaryBitwise);
        $instruction = new Restart();
        $meaning = new StatementSemantics(new LanguageProfile(GrammarRelease::MySql847), warnings: [new Notice($warning, instruction: $instruction)]);
        self::assertSame($instruction, $meaning->warningInstruction($warning));
        self::assertNull($meaning->warningSubject($warning));
    }

    public function testWarningSubjectRetainsTheOccurrenceThatRaisedIt(): void
    {
        $warning = new Deprecation(Deprecated::BinaryBitwise);
        $subject = new Restart();
        $meaning = new StatementSemantics(new LanguageProfile(GrammarRelease::MySql847), warnings: [new Notice($warning, $subject)]);
        self::assertSame($subject, $meaning->warningSubject($warning));
        self::assertNull($meaning->warningInstruction($warning));
        self::assertSame([$warning], $meaning->warnings);
    }

    public function testForQuerySelectsTheBoundQuerysOwnOutput(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 12 AS value');
        self::assertInstanceOf(QueryRoot::class, $statement);
        $meaning = $statement->statementInformation()->forQuery($statement->query);
        self::assertSame($statement->query->queryInformation(), $meaning->output);
        self::assertSame($statement->statementInformation()->connection, $meaning->connection);
        self::assertSame($statement->profile(), $meaning->profile);
    }

    public function testFieldReturnsTheProjectedColumnAtItsPosition(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 12 AS value');
        self::assertInstanceOf(QueryRoot::class, $statement);
        self::assertSame('value', $statement->statementInformation()->field(0)->name?->value);
    }
}
