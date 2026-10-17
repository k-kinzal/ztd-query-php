<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\InputErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

#[CoversClass(InputErrors::class)]
#[Small]
final class InputErrorsTest extends TestCase
{
    public function testViewUsesItsRetainedTargetBeforePreparingTheBody(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v'));
        $body = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()]));
        $problem = new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery($name, true, new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery($body));
        $session->text = 'unrelated invalid input';
        self::assertSame(1146, (new InputErrors())->view($problem, $session)->getCode());
        $session->query('CREATE TABLE v(a INT)');
        self::assertSame(1347, (new InputErrors())->view($problem, $session)->getCode());
        $session->query('DROP TABLE v; CREATE VIEW v AS SELECT 1');
        $session->text = 'unrelated invalid input';
        self::assertSame(1096, (new InputErrors())->view($problem, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testScalarBindsTheRetainedOperandWithoutLosingPriorNotices(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $session->diagnostics->warning(1681, 'Earlier grammar notice');
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('missing')), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar($row);

        self::assertSame(1054, (new InputErrors())->scalar($problem, $session)->getCode());
        self::assertSame([['Warning', 1681, 'Earlier grammar notice']], $session->diagnostics->conditions);
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testNoticeEndExcludesTheRefusedLookaheadAndKeepsCompletedOperands(): void
    {
        $errors = new InputErrors();

        self::assertSame(35, $errors->noticeEnd(new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash(), 36)));
        self::assertSame(36, $errors->noticeEnd(new \SqlSemantics\Construction\RejectedInput(new NotSupportedYet('example'), 36)));
    }

    public function testRefusedUsesTheTransientExcerptBoundaryForAnAlterUserHash(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = "ALTER USER u IDENTIFIED BY PASSWORD 'text'";
        $failure = new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash(), strlen('ALTER USER u IDENTIFIED BY PASSWORD '));
        $errors = new InputErrors();

        self::assertSame("You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near ''text'' at line 1", $errors->refused($failure, $sql, $session)->getMessage());
        self::assertSame(1235, $errors->refused(new \SqlSemantics\Construction\RejectedInput(new NotSupportedYet('example'), 0), '', $session)->getCode());
    }

    public function testSelectionReturnsItsConditionWithoutConsultingInputText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]);
        $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($row)]);

        self::assertSame(1241, (new InputErrors())->selection($selection, $session)->getCode());
        self::assertSame([], $session->diagnostics->conditions);
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testColumnResolvesItsDeclarationScopeWithoutInputText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $errors = new InputErrors();
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'));
        $problem = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute(new \SqlSemantics\Statement\Identifier\Name('a'), 'DEFAULT', $name);
        self::assertSame(1046, $errors->column($problem, $session)->getCode());
        $session->use('mysql');
        self::assertSame(1067, $errors->column($problem, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testSourceDistinguishesTypedSourceFailuresFromOtherDiagnostics(): void
    {
        $session = (new Instance())->connect();
        $errors = new InputErrors();
        self::assertSame(3945, $errors->source(new RefusedSourceNumber(SourceOptionKind::RequireRowFormat, new Numeral('2'), 0), $session)?->getCode());
        self::assertNull($errors->source(new NotSupportedYet('feature'), $session));
    }

    public function testErrorLocatesACompletedInternalEntry(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->text = "/* prefix */\nPARSE_GCOL_EXPR(missing)";
        $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry(13);

        self::assertStringContainsString("near 'PARSE_GCOL_EXPR(missing)' at line 2", (new InputErrors())->error($problem, $session)?->getMessage() ?? '');
    }

    public function testErrorFormatsARefusedSourceNumber(): void
    {
        $session = (new Instance())->connect();
        $session->text = '2 TRAILING';
        $problem = new RefusedSourceNumber(SourceOptionKind::GtidOnly, new Numeral('2'), 0);

        self::assertStringContainsString("near '2 TRAILING' at line 1", (new InputErrors())->error($problem, $session)?->getMessage() ?? '');
    }

    public function testErrorLocatesTheRefusedSubqueryWithoutKeepingItsText(): void
    {
        $session = (new Instance())->connect();
        $session->text = "PURGE BINARY LOGS BEFORE\n(SELECT 1)";
        $problem = new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery(25);

        self::assertStringContainsString("near '(SELECT 1)' at line 2", (new InputErrors())->error($problem, $session)?->getMessage() ?? '');
    }

    public function testErrorFormatsARefusedRole(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'SELECT TO user';
        $problem = new RoleOrPrivilegeMismatch('SELECT', true, 0);

        self::assertStringContainsString('Illegal authorization identifier', (new InputErrors())->error($problem, $session)?->getMessage() ?? '');
    }

    public function testNameFormatsTheEmptyObjectRoleWithoutInputText(): void
    {
        $session = (new Instance())->connect();
        $errors = array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind $kind): ?int => (new InputErrors())->error(new \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName($kind), $session)?->getCode(), \SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::cases());

        self::assertSame([1102, 1103, 1525], $errors);
        self::assertSame('', $session->text);
    }

    public function testErrorFormatsRefusedConnectionOptionsWithoutInputText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $errors = array_map(static fn (bool $analyze): ?int => (new InputErrors())->error(new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain($analyze), $session)?->getCode(), [true, false]);

        self::assertSame([1235, 6009], $errors);
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testErrorLeavesOtherDiagnosticsToTheirFamily(): void
    {
        self::assertNull((new InputErrors())->error(new NotSupportedYet('feature'), (new Instance())->connect()));
    }
    public function testErrorFormatsARestrictedQueryModifierWithoutReadingInput(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $problem = new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::HighPriority);

        self::assertSame(1234, (new InputErrors())->error($problem, $session)?->getCode());
    }

    public function testErrorResolvesTheViewDatabaseBeforeItsRefusedColumn(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $problem = new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')), new \SqlSemantics\Statement\Identifier\Name(''));
        self::assertSame(1046, (new InputErrors())->error($problem, $session)?->getCode());
        $session->run('CREATE DATABASE d; USE d');
        $session->text = 'unrelated invalid input';
        self::assertSame(1166, (new InputErrors())->error($problem, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testUnionFormatsADirectConditionWithoutReadingTheRequestLabel(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->text = 'unrelated invalid input';
        $error = (new InputErrors())->union(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion(), $session);
        self::assertSame(1064, $error->getCode());
        self::assertStringContainsString("near '' at line 1", $error->getMessage());
    }

    public function testQueryMapsTheRetainedModifierPlacement(): void
    {
        $session = (new Instance())->connect();
        $problem = new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::HighPriority);
        self::assertSame(1234, (new InputErrors())->query($problem, $session)?->getCode());
    }

}
