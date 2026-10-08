<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\PreparedCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(PreparedCommand::class)]
#[Small]
final class PreparedCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new PreparedCommand())->clearsDiagnostics());
    }

    public function testExecutePreparesAStatementWithItsMarkers(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("PREPARE s FROM 'SELECT ? + 1 AS x, ?'")[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(['s' => ['SELECT ? + 1 AS x, ?', 2]], $session->prepared);
    }

    public function testExecuteRefusesSeveralStatementsAtTheSecond(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("near 'SELECT 2' at line 1");

        $session->query("PREPARE s FROM 'SELECT 1; SELECT 2'");
    }

    public function testExecuteRefusesToPrepareAPrepareStatement(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1295);

        $session->query("PREPARE s FROM 'PREPARE t FROM ''SELECT 1'''");
    }

    public function testExecuteForgetsTheStatementOfTheNameWhenPreparingFails(): void
    {
        $session = (new Instance())->connect();
        $session->query("PREPARE s FROM 'SELECT 1'");

        $session->run("PREPARE s FROM 'bad'");

        self::assertSame([], $session->prepared);
    }

    public function testExecutePreparesTheTextNullOfAnUnsetVariable(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("near 'NULL' at line 1");

        $session->query('PREPARE s FROM @undefined');
    }

    public function testExecuteDeallocatesByNameWithoutCase(): void
    {
        $session = (new Instance())->connect();
        $session->query("PREPARE s FROM 'SELECT 1'");

        $session->query('DEALLOCATE PREPARE S');

        self::assertSame([], $session->prepared);
    }

    public function testExecuteRefusesToDeallocateAnUnknownStatement(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1243);
        $this->expectExceptionMessage('Unknown prepared statement handler (SUPER) given to DEALLOCATE PREPARE');

        $session->query('DROP PREPARE SUPER');
    }

    public function testRunBindsTheUserVariables(): void
    {
        $session = (new Instance())->connect();
        $session->query("PREPARE s FROM 'SELECT ? + 1 AS x, ?'");
        $session->query("SET @a = 5, @b = 'q'");

        $result = $session->query('EXECUTE s USING @a, @b')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame([['6', 'q']], $result->rows);
    }

    public function testRunRefusesAnotherNumberOfVariables(): void
    {
        $session = (new Instance())->connect();
        $session->query("PREPARE s FROM 'SELECT ?'");

        $this->expectExceptionCode(1210);
        $this->expectExceptionMessage('Incorrect arguments to EXECUTE');

        (new PreparedCommand())->run(new Execute(new Name('s')), $session);
    }

    public function testRunRefusesAnUnknownStatement(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1243);
        $this->expectExceptionMessage('Unknown prepared statement handler (LOCAL) given to EXECUTE');

        $session->query('EXECUTE LOCAL');
    }

    public function testParameterTypesEachValueAsTheServerBindsIt(): void
    {
        $session = (new Instance())->connect();
        $command = new PreparedCommand();
        $connection = Collation::known('utf8mb4_0900_ai_ci');

        $string = $command->parameter($session, 'x', Domain::string(10, Collation::known('latin1_swedish_ci')));
        $null = $command->parameter($session, null, Domain::integer());

        self::assertSame([Field::VarString, 16383, $connection], [$string[1]->field, $string[1]->length, $string[1]->collation]);
        self::assertSame([null, Field::VarString, true], [$null[0], $null[1]->field, $null[1]->nullable]);
        self::assertSame([Field::NewDecimal, 30], [$command->parameter($session, '1.5', Domain::decimal(2, 1))[1]->field, $command->parameter($session, '1.5', Domain::decimal(2, 1))[1]->decimals]);
    }
}
