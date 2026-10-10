<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Identifiers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;

#[CoversClass(Identifiers::class)]
#[Small]
final class IdentifiersTest extends TestCase
{
    public function testCheckRefusesADatabaseNameLongerThan64CharactersOfAMaintenanceStatement(): void
    {
        $session = (new Instance())->connect();
        $name = str_repeat('d', 65);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1059);
        $this->expectExceptionMessage("Identifier name '" . $name . "' is too long");

        $session->query('CHECKSUM TABLE ' . $name . '.t');
    }

    public function testCheckRefusesTheDatabaseBeforeTheTable(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectExceptionMessage("Identifier name '" . str_repeat('d', 65) . "' is too long");

        $session->query('ANALYZE TABLE ' . str_repeat('d', 65) . '.' . str_repeat('t', 65));
    }

    public function testCheckTakesAnAliasOf65Characters(): void
    {
        $session = (new Instance())->connect();

        $session->query('SELECT 1 FROM (SELECT 1) AS ' . str_repeat('a', 65));

        $this->addToAssertionCount(1);
    }

    public function testCheckTakes64Characters(): void
    {
        $session = (new Instance())->connect();

        (new Identifiers())->check($session->analyze('SELECT * FROM ' . str_repeat('d', 64) . '.t')->statement, GrammarRelease::MySql847);

        $this->addToAssertionCount(1);
    }

    public function testNamesChecksTheDatabaseOfAColumnOnlyFromMySql80(): void
    {
        $session = (new Instance())->connect();
        $column = (new Walker())->find($session->analyze('SELECT d.t.a')->statement, ColumnUse::class)[0];

        self::assertSame([0, 1], [count((new Identifiers())->names($column, GrammarRelease::MySql5744)), count((new Identifiers())->names($column, GrammarRelease::MySql8044))]);
    }

    public function testNamesLeavesDropFunctionIfExistsInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $statement = $session->analyze('DROP FUNCTION IF EXISTS f')->statement;

        self::assertSame([[], 2], [(new Identifiers())->names($statement, GrammarRelease::MySql5651), count((new Identifiers())->names($statement, GrammarRelease::MySql5744))]);
    }

    public function testDefinitionsAnswersTheConstraintThenTheIndexName(): void
    {
        $session = (new Instance())->connect();
        $index = (new Walker())->find($session->analyze('CREATE TABLE t (a INT, CONSTRAINT c UNIQUE KEY k (a))')->statement, IndexDefinition::class)[0];

        self::assertSame(['c', 'k'], array_map(static fn ($name): ?string => $name?->value, (array) (new Identifiers())->definitions($index, false)));
    }

    public function testDefinitionsIgnoresTheNameOfACheckConstraintInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT, CONSTRAINT ' . str_repeat('c', 65) . ' CHECK (a > 0))');

        $this->addToAssertionCount(1);
    }

    public function testPropertiesAnswersTheDatabaseOfAStatementThatNamesOne(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['d'], array_map(static fn ($name): ?string => $name?->value, (new Identifiers())->properties($session->analyze('SHOW TABLES FROM d')->statement)));
    }

    public function testCheckRefusesAColumnNameOfADefinition(): void
    {
        $session = (new Instance('9.1.0'))->connect();

        $this->expectExceptionCode(1059);

        $session->query('CREATE TABLE t (' . str_repeat('c', 65) . ' INT)');
    }
}
