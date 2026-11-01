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
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Restart;
use SqlSemantics\Platform\MySql\Statement\SqlStatement;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(SqlStatement::class)]
#[Medium]
final class SqlStatementTest extends TestCase
{
    public function testInitializeStatementInformationCannotChangePublishedMeaning(): void
    {
        $statement = new Restart();
        $information = new StatementSemantics(new LanguageProfile(GrammarRelease::MySql847), Charset::known('utf8mb4'));
        $statement->initializeStatementInformation($information);
        $statement->initializeStatementInformation($information);
        self::assertSame($information, $statement->statementInformation());
        $this->expectExceptionMessage('A statement occurrence is resolved once.');
        $statement->initializeStatementInformation(new StatementSemantics(new LanguageProfile(GrammarRelease::MySql910), Charset::known('utf8mb4')));
    }

    public function testStatementInformationRequiresCompletedConstruction(): void
    {
        $this->expectExceptionMessage('A statement must be resolved before use.');
        (new Restart())->statementInformation();
    }

    public function testHasStatementInformationDistinguishesPendingAndCompletedConstruction(): void
    {
        $statement = new Restart();
        self::assertFalse($statement->hasStatementInformation());
        $statement->initializeStatementInformation(new StatementSemantics(new LanguageProfile(GrammarRelease::MySql847), Charset::known('utf8mb4')));
        self::assertTrue($statement->hasStatementInformation());
    }

    public function testToStringUsesTheConcreteStatementOperands(): void
    {
        $statement = new Restart(new StatementSemantics(new LanguageProfile(GrammarRelease::MySql847), Charset::known('utf8mb4')));
        self::assertSame('RESTART', $statement->toString());
    }

    public function testProfileRetainsTheSelectedGrammar(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $statement = new Restart(new StatementSemantics($profile, Charset::known('utf8mb4')));
        self::assertSame($profile, $statement->profile());
    }

    public function testDeclarationsRemainOwnedByTheConcreteDefinition(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE catalog_entry (id INT)');
        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame('catalog_entry', $statement->declarations()[0]->name->name->value);
        self::assertSame($statement->declarations(), $statement->declarations());
    }
    public function testDiagnosticsPreserveAnUnresolvedColumn(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT missing FROM absent', []);
        self::assertNotEmpty($statement->diagnostics());
        self::assertSame('Relation absent does not exist.', $statement->diagnostics()[0]->message());
    }

    public function testWarningsRetainTheSpellingCondition(): void
    {
        $statement = (new Semantics(Dialect::MySql, '8.4.7'))->analyze('SELECT 1 AS full');
        self::assertCount(1, $statement->warnings());
        self::assertStringContainsString('FULL', strtoupper($statement->warnings()[0]->message()));
    }

    public function testNoticesAssociateTheWarningWithItsOccurrence(): void
    {
        $statement = (new Semantics(Dialect::MySql, '8.4.7'))->analyze('SELECT 1 AS full');
        self::assertCount(1, $statement->notices());
        self::assertSame($statement->warnings()[0], $statement->notices()[0]->warning);
        self::assertNotNull($statement->notices()[0]->subject);
    }

    public function testOutputDescribesTheConcreteQuery(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS n');
        self::assertNotNull($statement->output());
        self::assertSame($statement->field(0), $statement->output()->projection[0]);
    }

}
