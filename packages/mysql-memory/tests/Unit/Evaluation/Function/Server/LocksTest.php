<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Server;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Server\Locks;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Locks::class)]
#[Small]
final class LocksTest extends TestCase
{
    public function testRoutinesNameTheLockingFunctions(): void
    {
        self::assertSame(['GET_LOCK', 'RELEASE_LOCK', 'RELEASE_ALL_LOCKS', 'IS_FREE_LOCK', 'IS_USED_LOCK'], array_map(static fn ($routine): string => $routine->name, (new Locks())->routines()));
    }

    public function testRoutinesShareTheLocksBetweenTheSessionsOfAnInstance(): void
    {
        $instance = new Instance();
        $first = $instance->connect();
        $second = $instance->connect();
        $taken = $first->query("SELECT GET_LOCK('a', 0), GET_LOCK('A', 0), IS_FREE_LOCK('a'), IS_USED_LOCK('a')")[0];
        $refused = $second->query("SELECT GET_LOCK('a', 0), RELEASE_LOCK('a'), RELEASE_LOCK('b'), IS_USED_LOCK('a')")[0];
        $released = $first->query("SELECT RELEASE_LOCK('a'), RELEASE_LOCK('a'), RELEASE_LOCK('a'), RELEASE_ALL_LOCKS()")[0];

        self::assertInstanceOf(ResultSet::class, $taken);
        self::assertInstanceOf(ResultSet::class, $refused);
        self::assertInstanceOf(ResultSet::class, $released);
        self::assertSame([['1', '1', '0', '1']], $taken->rows);
        self::assertSame([['0', '0', null, '1']], $refused->rows);
        self::assertSame([['1', '1', null, '0']], $released->rows);
    }

    public function testRoutinesReleaseTheLocksOfASessionThatEnds(): void
    {
        $instance = new Instance();
        $first = $instance->connect();
        $second = $instance->connect();
        $first->query("SELECT GET_LOCK('a', 0), GET_LOCK('b', 0)");
        $first->close();
        $result = $second->query("SELECT IS_FREE_LOCK('a'), IS_FREE_LOCK('b')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testRoutinesTypeTheResultsAsTheServerDoes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT GET_LOCK('a', 0), IS_USED_LOCK('a'), RELEASE_ALL_LOCKS()")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[1, 21, 21], [Field::LongLong, Field::LongLong, Field::LongLong]], [array_map(static fn ($column): int => $column->length, $result->columns), array_map(static fn ($column): Field => $column->type, $result->columns)]);
    }

    public function testGetWaitsForTheTimeoutOnTheClockOfTheServer(): void
    {
        $instance = new Instance();
        $holder = $instance->connect();
        $holder->query("SELECT GET_LOCK('a', 0)");
        $result = $instance->connect()->query("SELECT GET_LOCK('a', 3600), GET_LOCK('a', -1), GET_LOCK('a', '5x')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', '0']], $result->rows);
        self::assertSame([3605.0, 1], [$instance->registry->threads->passed, $instance->registry->threads->owner('a')]);
    }

    public function testRoutinesReleaseTheLocksOfASessionNothingRefersTo(): void
    {
        $instance = new Instance();
        $instance->connect()->query("SELECT GET_LOCK('a', 0)");

        self::assertSame([], $instance->registry->threads->locks);
    }

    public function testGetHoldsOneLockPerSessionInMySql56(): void
    {
        $instance = new Instance('5.6.51');
        $session = $instance->connect();
        $result = $session->query("SELECT GET_LOCK('a', 0), GET_LOCK('a', 0), GET_LOCK('b', 0), IS_FREE_LOCK('a'), RELEASE_LOCK('b'), RELEASE_LOCK('b')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', '1', '1', null]], $result->rows);
    }

    public function testThreadsAnswersTheThreadsOfTheInstance(): void
    {
        $instance = new Instance();
        $variables = new Variables($instance->catalog, $instance->globals, $instance);

        self::assertSame($instance->registry->threads, (new Locks())->threads(new Frame(new Context(new SqlModes([]), new Diagnostics(), $variables, 0.0))));
    }

    public function testKeyFoldsLetterCaseButKeepsAccentsAndSpaces(): void
    {
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), (new Instance())->connect()->variables, 0.0));
        $text = static fn (string $value): Constant => new Constant(Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $value);

        self::assertSame(['é ', 'name', '12'], [(new Locks())->key($frame, $text('É ')), (new Locks())->key($frame, new Constant(Domain::string(4, Collation::binary()), 'NAME')), (new Locks())->key($frame, new Constant(Domain::integer(Field::LongLong, 2), 12))]);
    }

    public function testKeyRefusesAnEmptyName(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect user-level lock name ''. The name is empty, NULL, or can not be expressed in the current character-set.");
        $session->query("SELECT GET_LOCK('', 0)");
    }

    public function testKeyRefusesANameUtf8mb3CannotHold(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect user-level lock name '\\xFF'.");
        $session->query("SELECT IS_FREE_LOCK(_binary x'ff')");
    }

    public function testKeyRefusesANameOfMoreThan64Characters(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("User-level lock name '" . str_repeat('é', 65) . "' should not exceed 64 characters.");
        $session->query("SELECT RELEASE_LOCK(REPEAT('é', 65))");
    }

    public function testKeyAnswersNullForAnEmptyNameInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $result = $session->query("SELECT GET_LOCK(NULL, 0), IS_FREE_LOCK(''), GET_LOCK(REPEAT('a', 65), 0)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, '1']], $result->rows);
    }

    public function testWrongNamesTheNameInTheWordsOfTheRelease(): void
    {
        self::assertSame(["Incorrect user-level lock name 'x'.", 3057], [(new Locks())->wrong('x', GrammarRelease::MySql5744)->getMessage(), (new Locks())->wrong('x', GrammarRelease::MySql847)->getCode()]);
    }
}
