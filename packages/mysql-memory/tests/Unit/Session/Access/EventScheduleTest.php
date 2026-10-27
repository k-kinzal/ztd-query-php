<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\EventSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(EventSchedule::class)]
#[Small]
final class EventScheduleTest extends TestCase
{
    public function testStatementLeavesOtherStatementRolesToTheirOwnResolution(): void
    {
        $session = (new Instance())->connect();
        $selection = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Variable\SystemVariable(new Name('HELP')))]));
        (new EventSchedule())->statement($selection, $session);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testCheckRequiresADatabaseForAnUnqualifiedScheduleTable(): void
    {
        $read = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], new M\Relation\TableReference(new QualifiedName(new Name('t')))));
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new EventSchedule())->check([$read], $session);
    }

    public function testCheckLeavesQualifiedNamesAndSelectedDatabasesToOrdinaryResolution(): void
    {
        $read = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], new M\Relation\TableReference(new QualifiedName(new Name('t'), new Name('d')))));
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        (new EventSchedule())->check([$read], $session);
        $session->use('d');
        (new EventSchedule())->check([$read], $session);
        self::assertSame([], $session->diagnostics->conditions);
    }
    public function testCheckResolvesVariablesInsideANonScalarScheduleBeforeItsWidth(): void
    {
        $row = new M\Expression\Row([new M\Variable\SystemVariable(new Name('HELP')), new M\Literal\NumberLiteral('1')]);
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1193);
        $this->expectExceptionMessage("Unknown system variable 'HELP'");
        (new EventSchedule())->check([$row], $session);
    }

    public function testStatementUsesTheScheduleOrderWithoutReadingTheRequestLabel(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CREATE EVENT d.e ON SCHEDULE AT (SELECT 1 FROM missing)+@@HELP DO SELECT 1');
        self::assertInstanceOf(M\Routine\CreateEvent::class, $statement);
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new EventSchedule())->statement($statement, $session);
    }

}
