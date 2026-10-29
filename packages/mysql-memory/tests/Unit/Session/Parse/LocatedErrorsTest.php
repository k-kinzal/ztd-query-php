<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\LocatedErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;

#[CoversClass(LocatedErrors::class)]
#[Small]
final class LocatedErrorsTest extends TestCase
{
    public function testParsedResolvesAnInvalidLegacyGrantTargetBeforeItsCondition(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->text = 'unrelated input';
        $grant = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select, [new \SqlSemantics\Statement\Identifier\Name('c')])], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel(), [new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), $grant);
        $notice = $statement->statementInformation()->warnings[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notice);
        self::assertSame(1046, (new LocatedErrors())->parsed($notice, $statement, $session)->getCode());
        $session->variables->database = 'd';
        self::assertSame(1144, (new LocatedErrors())->parsed($notice, $statement, $session)->getCode());
        self::assertSame('unrelated input', $session->text);
    }

    public function testPrivilegeResolvesTheEnclosingProgramsDatabase(): void
    {
        $session = (new Instance('5.6.51', databases: ['d']))->connect();
        $statement = $session->analyze('CREATE PROCEDURE d.p() GRANT SELECT(c) ON * TO u');
        $warning = $statement->statementInformation()->warnings[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $warning);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel::class, $warning->problem);
        self::assertSame(1144, (new LocatedErrors())->privilege($warning->problem, $statement, $session)->getCode());
    }

    public function testParsedResolvesAColumnConditionWithinItsQualifiedProgram(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect();
        $statement = $session->analyze('CREATE PROCEDURE d.p() BEGIN DECLARE x INT; BEGIN DECLARE y INT; ALTER TABLE t ADD a BIT DEFAULT CURRENT_TIMESTAMP; END; END');
        $notice = $statement->statementInformation()->warnings[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notice);
        self::assertSame(1067, (new LocatedErrors())->parsed($notice, $statement, $session)->getCode());
        self::assertSame('', $session->variables->database);
    }

    public function testConditionQuotesTheTokenAfterTheCompleteAssignedExpression(): void
    {
        $session = (new Instance('5.7.44', databases: ['d']))->connect(database: 'd');
        $session->text = 'GET DIAGNOSTICS CONDITION @n := (((1))) @m = MESSAGE_TEXT';
        $statement = $session->analyze($session->text);
        $warning = $statement->statementInformation()->warnings[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $warning);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\AssignedConditionOperand::class, $warning->problem);
        self::assertStringEndsWith("near '@m = MESSAGE_TEXT' at line 1", (new LocatedErrors())->condition($warning->problem, $session)->getMessage());
    }

    public function testParsedPreservesTheSpecificConditionWithoutALocation(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT 1');
        $notice = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation('missing'));
        $error = (new LocatedErrors())->parsed($notice, $statement, $session);
        self::assertSame(1273, $error->getCode());
        self::assertSame("Unknown collation: 'missing'", $error->getMessage());
    }

    public function testParsedRequiresTheReferencedDatabaseBeforeALockingCondition(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT 1 FROM t');
        $notice = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse(\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::UnknownLockedTable, new \SqlSemantics\Statement\Identifier\Name('missing')));
        $this->expectExceptionCode(1046);
        (new LocatedErrors())->parsed($notice, $statement, $session);
    }

    public function testSubqueryUsesTheNestedOccurrenceInsteadOfTheEnclosingCall(): void
    {
        $session = (new Instance())->connect();
        $sql = 'SELECT ABS((SELECT 1))';
        $operation = $session->semantics()->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $expression = $operation->field(0)->expression;
        self::assertInstanceOf(\SqlSemantics\Statement\Operand::class, $expression);
        self::assertSame(strpos($sql, '(SELECT'), (new LocatedErrors())->subquery($expression, null));
        self::assertNull((new LocatedErrors())->subquery(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), null));
    }

    public function testErrorQuotesTheOriginalSpellingAndLine(): void
    {
        $session = (new Instance())->connect();
        $session->text = "ALTER TABLE missing\nPARTITION BY HASH ( /* marker */ (USER ( )))";
        $statement = $session->analyze($session->text);
        $problems = array_values(array_filter($statement->statementInformation()->diagnostics, static fn ($problem): bool => $problem instanceof InvalidPartitionExpression));
        self::assertCount(1, $problems);
        $error = (new LocatedErrors())->error($problems[0], $statement, $session);
        self::assertNotNull($error);
        self::assertSame(1064, $error->getCode());
        self::assertStringEndsWith("near '(USER ( )))' at line 2", $error->getMessage());
    }

    public function testErrorIgnoresUnrelatedDiagnostics(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT missing');
        self::assertNull((new LocatedErrors())->error($operation->statementInformation()->diagnostics[0], $operation, $session));
    }

    public function testLocatedUsesTheRefusedDefinitionsOriginalOccurrence(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->run("CREATE TABLE t\nPARTITION BY HASH( ( USER ( ) ) )");

        self::assertSame([['Error', 1064, "Constant, random or timezone-dependent expressions in (sub)partitioning function are not allowed near '( USER ( ) ) )' at line 2"]], $session->diagnostics->conditions);
    }

}
