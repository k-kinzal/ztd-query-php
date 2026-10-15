<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use Fuzz\Structure\Execution;
use MySqlMemory\Command\InternalCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

#[CoversClass(InternalCommand::class)]
#[Medium]
final class InternalCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{Statement, string, string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'debug tree' => [new ShowParseTree(new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new Select([], [new SelectExpression(new ColumnUse(new Name('missing')))]))), '8.4.7', "near 'PARSE_TREE SELECT missing' at line 1"];
    }

    public function testOperandsCheckConstructedAccountNamesBeforeTheDebugOnlyRefusal(): void
    {
        $session = (new Instance())->connect();
        $statement = new ShowParseTree(new \SqlSemantics\Platform\MySql\Statement\Account\DropUser(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name(str_repeat('u', 33)))]));
        $operation = new Operation($session->semantics()->context([], true, null, $session->resolution([])), $statement);
        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage('is too long for user name');

        (new InternalCommand())->operands($operation);
    }

    public function testDirectExecutionChecksAnAccountBeforeRefusingTheDebugCommand(): void
    {
        $statement = new ShowParseTree(new \SqlSemantics\Platform\MySql\Statement\Account\DropUser(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name(str_repeat('u', 33)))]));
        $this->expectExceptionCode(1470);

        (new Execution())->run($statement);
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new InternalCommand())->clearsDiagnostics());
    }

    #[DataProvider('providerStatements')]
    public function testExecuteRejectsAnInternalClientRequest(Statement $statement, string $release, string $message): void
    {
        $session = (new Instance($release))->connect();
        $operation = new Operation($session->semantics()->context([], true, null, $session->resolution([])), $statement);
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
        $operation = new Operation($session->semantics()->context([], true, null, $session->resolution([])), $statement);
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

        (new Execution())->run($statement, $release);
    }
}
