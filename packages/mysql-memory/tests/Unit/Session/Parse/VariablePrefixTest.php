<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\VariablePrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariablePrefix::class)]
#[Small]
final class VariablePrefixTest extends TestCase
{
    public function testBeforeResolvesACompletedAssignmentTargetWithoutExecutingIt(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $sql = 'SET @prefix_probe=1, ROLE unexpected';
        $tokens = $session->semantics()->parser()->tokenize($sql);
        $error = (new VariablePrefix())->before($tokens, $sql, (int) strpos($sql, 'unexpected'), $session);

        self::assertSame("Unknown system variable 'ROLE'", $error?->getMessage());
        self::assertSame([], $session->variables->user);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testBeforeLeavesKnownTargetsAndIncompletePrefixesToTheParser(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $known = 'SET autocommit ';
        $incomplete = 'SET @';

        self::assertNull((new VariablePrefix())->before($session->semantics()->parser()->tokenize($known), $known, strlen($known), $session));
        self::assertNull((new VariablePrefix())->before($session->semantics()->parser()->tokenize($incomplete), $incomplete, strlen($incomplete), $session));
    }

    public function testBeforeResolvesTheTargetBeforeReadingAnIncompleteRightHandSide(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $sql = 'SET unknown_prefix=(';
        $error = (new VariablePrefix())->before($session->semantics()->parser()->tokenize($sql), $sql, strlen($sql), $session);

        self::assertSame("Unknown system variable 'unknown_prefix'", $error?->getMessage());
        self::assertSame([[1193, "Unknown system variable 'unknown_prefix'"]], $error->following);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testBeforePreservesAnEarlierUnknownReadWithoutRepeatingItsCondition(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $sql = 'SET @x=@@another_missing,unknown_prefix 1';
        $error = (new VariablePrefix())->before($session->semantics()->parser()->tokenize($sql), $sql, strlen($sql) - 1, $session);

        self::assertSame("Unknown system variable 'another_missing'", $error?->getMessage());
        self::assertSame([], $error->following);
        self::assertSame([], $session->variables->user);
    }

    public function testBeforeDoesNotOverrideOtherReleasesOrStatements(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $legacy = (new Instance('5.6.51'))->connect();
        $set = 'SET ROLE ';
        $select = 'SELECT ROLE ';

        self::assertNull((new VariablePrefix())->before($session->semantics()->parser()->tokenize($set), $set, strlen($set), $session));
        self::assertNull((new VariablePrefix())->before($legacy->semantics()->parser()->tokenize($select), $select, strlen($select), $legacy));
    }

    public function testBeforeLeavesRefusedNamesOperandsToTheInputReader(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $sql = 'SET NAMES=1 TRAILING';

        self::assertNull((new VariablePrefix())->before($session->semantics()->parser()->tokenize($sql), $sql, strlen($sql), $session));
    }
}
