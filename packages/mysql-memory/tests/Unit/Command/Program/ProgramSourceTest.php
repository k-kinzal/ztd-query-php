<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\ProgramSource;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ProgramSource::class)]
#[Small]
final class ProgramSourceTest extends TestCase
{
    public function testBodyKeepsCommentsAndLeavesOutTrailingWhiteSpace(): void
    {
        $session = (new Instance())->connect();
        $session->text = "CREATE PROCEDURE p1 ( a  INT /* c */ ) select   1   /* x */  \n";

        self::assertSame('select   1   /* x */', (new ProgramSource($session->text, $session->semantics()->parser()->parse($session->text)))->body('stored_routine_body'));
    }

    public function testTriggerPreservesTheHeaderButRemovesItsOrderingClause(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->text = 'create trigger d.z before insert on d.t for each row follows y begin end';
        self::assertSame('trigger d.z before insert on d.t for each row begin end', (new ProgramSource($session->text, $session->semantics()->parser()->parse($session->text)))->trigger());
    }

    public function testBetweenAnswersTheTextBetweenTheParentheses(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'CREATE PROCEDURE p1 ( a  INT /* c */ ) SELECT 1';

        self::assertSame(' a  INT /* c */ ', (new ProgramSource($session->text, $session->semantics()->parser()->parse($session->text)))->between('sp_tail'));
    }

    public function testDefinerAnswersTheAccountOrTheSessionAccount(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame([['bob', '%'], ['root', '%']], [ProgramSource::definer(new AccountName(new Name('bob')), $session, $context), ProgramSource::definer(new CurrentUser(), $session)]);
        self::assertSame([['Note', 1449, "The user specified as a definer ('bob'@'%') does not exist"]], $session->diagnostics->conditions);
    }

    public function testDefinerUsesTheNormalizedAccountOperands(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid SQL';

        self::assertSame(['bob', 'host'], ProgramSource::definer(new AccountName(new Name("\rbob\t"), new Name(' HOST ')), $session));
    }

    public function testEnabledReadsABooleanVariable(): void
    {
        self::assertSame([true, true, false, false], [ProgramSource::enabled('ON'), ProgramSource::enabled(1), ProgramSource::enabled('OFF'), ProgramSource::enabled(null)]);
    }

    public function testDatabaseRefusesAnUnqualifiedNameWithoutADatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1046);

        ProgramSource::database(null, $session);
    }

    public function testCharsetsAnswersTheCharacterSetsOfTheSessionAndTheDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d CHARACTER SET latin1');

        self::assertSame(['utf8mb4', 'utf8mb4_0900_ai_ci', 'latin1_swedish_ci'], ProgramSource::charsets($session, 'd'));
    }

    public function testNowWritesTheFractionalDigitsAsked(): void
    {
        self::assertSame([19, 22], [strlen(ProgramSource::now()), strlen(ProgramSource::now(2))]);
    }
}
