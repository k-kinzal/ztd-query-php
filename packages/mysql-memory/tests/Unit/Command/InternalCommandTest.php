<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Command\InternalCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(InternalCommand::class)]
#[Medium]
final class InternalCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{Statement, string, string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'debug tree' => [new ShowParseTree(new QueryRoot(new Select([], [new SelectExpression(new ColumnUse(new Name('missing')))]))), '8.4.7', "near 'PARSE_TREE SELECT missing' at line 1"];
    }
    public function testInputRefusesALongAccountBeforeTheDebugCommand(): void
    {
        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage('is too long for user name');
        (new Instance())->connect()->query("SHOW PARSE_TREE DROP USER '" . str_repeat('u', 33) . "'");
    }
    public function testInputRefusesAnInvalidHostBeforeTheDebugCommand(): void
    {
        $this->expectExceptionCode(1105);
        $this->expectExceptionMessage("Malformed hostname (illegal symbol: '@')");
        (new Instance())->connect()->query("SHOW PARSE_TREE DROP USER 'u'@'a@b'");
    }
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new InternalCommand())->clearsDiagnostics());
    }
    #[DataProvider('providerStatements')]
    public function testExecuteRejectsAnInternalClientRequest(Statement $statement, string $release, string $message): void
    {
        $session = (new Instance($release))->connect();
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context([], true, null, $session->resolution([])), $statement, new \SqlSemantics\Statement\Source\SourceMap());
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage($message);
        (new InternalCommand())->execute($operation, $session, $context, new Connection($session->variables, $context));
    }
    #[DataProvider('providerStatements')]
    public function testErrorHasTheNativeErrorNumberAndState(Statement $statement, string $release, string $message): void
    {
        $session = (new Instance($release))->connect();
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context([], true, null, $session->resolution([])), $statement, new \SqlSemantics\Statement\Source\SourceMap());
        $error = (new InternalCommand())->error($operation);
        self::assertSame(1064, $error->getCode());
        self::assertSame('42000', $error->sqlState());
        self::assertStringContainsString($message, $error->getMessage());
    }
    #[DataProvider('providerStatements')]
    public function testDirectExecutionRejectsTheRequestBeforeItsMissingColumn(Statement $statement, string $release, string $message): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage($message);
        $session1 = (new Instance($release))->connect();
        $statement1 = $statement;
        $session1->activity->begin();
        StatementCounters::received($session1);

        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, \SqlSemantics\Contract\ParameterStyle::Native));
        (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1), []);
    }
}
