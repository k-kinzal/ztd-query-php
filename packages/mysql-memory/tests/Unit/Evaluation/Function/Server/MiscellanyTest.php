<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Server;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Server\Miscellany;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Variables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Miscellany::class)]
#[Small]
final class MiscellanyTest extends TestCase
{
    public function testRoutinesNameTheMiscellaneousFunctions(): void
    {
        self::assertSame(['ANY_VALUE', 'NAME_CONST', 'ICU_VERSION', 'ROLES_GRAPHML'], array_map(static fn ($routine): string => $routine->name, (new Miscellany())->routines()));
    }

    public function testRoutinesAnswerAnyValueOfAGroup(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (i INT NOT NULL, j INT); INSERT INTO t VALUES (1, 2), (1, 3)');
        $result = $session->query('SELECT i, ANY_VALUE(j) FROM t GROUP BY i ORDER BY ANY_VALUE(j)')[0];
        $literal = $session->query('SELECT ANY_VALUE(1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $literal);
        self::assertSame([['1', '2']], $result->rows);
        self::assertSame([2, Field::LongLong], [$literal->columns[0]->length, $literal->columns[0]->type]);
    }

    public function testRoutinesNameTheColumnOfNameConst(): void
    {
        $result = (new Instance())->connect()->query("SELECT NAME_CONST('x', -1), NAME_CONST('y', 18446744073709551615), NAME_CONST(1.5, 'a' COLLATE utf8mb4_bin)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['x', 'y', '1.5'], [['-1', '-1', 'a']]], [array_map(static fn ($column): string => $column->name, $result->columns), $result->rows]);
    }

    public function testRoutinesAnswerTheIcuVersionOfTheRelease(): void
    {
        $result = (new Instance())->connect()->query('SELECT ICU_VERSION()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['77.1']], $result->rows);
    }

    public function testConstantsRefusesAValueThatIsNoLiteral(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to NAME_CONST');
        $session->query("SELECT NAME_CONST('x', 1 + 1)");
    }

    public function testConstantsRefusesANullName(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("The 'NAME_CONST' syntax is reserved for purposes internal to the MySQL server");
        $session->query('SELECT NAME_CONST(NULL, 1)');
    }

    public function testIcuAnswersTheVersionEachReleaseBundles(): void
    {
        self::assertSame(['77.1', '77.1', '73.1'], [(new Miscellany())->icu(GrammarRelease::MySql8044), (new Miscellany())->icu(GrammarRelease::MySql847), (new Miscellany())->icu(GrammarRelease::MySql910)]);
    }

    public function testGraphWritesTheAccountsAndTheirRoles(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE ROLE r1, 'r<2'@'h'; GRANT r1 TO 'r<2'@'h' WITH ADMIN OPTION");
        $graph = (new Miscellany())->graph($session->variables);

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<graphml xmlns="http://graphml.graphdrawing.org/xmlns"', $graph);
        self::assertStringContainsString('    <node id="n0">' . "\n" . '      <data key="key1">`mysql.infoschema`@`localhost`</data>' . "\n" . '    </node>', $graph);
        self::assertStringContainsString('<node id="n4">' . "\n" . '      <data key="key1">`root`@`%`</data>', $graph);
        self::assertStringContainsString('<data key="key1">`r&lt;2`@`h`</data>', $graph);
        self::assertStringEndsWith('    <edge id="e0" source="n6" target="n5">' . "\n" . '      <data key="key0">1</data>' . "\n" . '    </edge>' . "\n" . '  </graph>' . "\n" . '</graphml>' . "\n", $graph);
    }

    public function testGraphIsEmptyForASessionThatMayNotReadIt(): void
    {
        $instance = new Instance();
        $instance->connect()->query("CREATE USER u IDENTIFIED BY 'p'");

        self::assertSame('<?xml version="1.0" encoding="UTF-8"?><graphml />', (new Miscellany())->graph($instance->connect('u', '%')->variables));
    }

    public function testAdministersTellsWhetherTheAccountHoldsRoleAdmin(): void
    {
        $instance = new Instance();
        $root = $instance->connect();
        $root->query("CREATE USER u IDENTIFIED BY 'p'; CREATE ROLE admin; GRANT ROLE_ADMIN ON *.* TO admin; GRANT admin TO u");
        $user = $instance->connect('u', '%');
        $before = (new Miscellany())->administers($instance->accounts, $user->variables);
        $user->query('SET ROLE admin');

        self::assertSame([true, false, true, true], [(new Miscellany())->administers($instance->accounts, $root->variables), $before, (new Miscellany())->administers($instance->accounts, $user->variables), (new Miscellany())->administers($instance->accounts, new Variables($instance->catalog, $instance->globals, $instance))]);
    }
}
