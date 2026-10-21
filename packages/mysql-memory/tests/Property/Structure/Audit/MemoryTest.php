<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit;

use Fuzz\Structure\Audit\Memory;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * A terminal SQL condition is observed completely rather than discarded or classified as a crash.
 */
#[CoversNothing]
#[Small]
final class MemoryTest extends TestCase
{
    /**
     * Direct execution observes a closed connection before resolving another structure.
     */
    public function testObserveRejectsFurtherDirectStructuresAfterTheConnectionHasClosed(): void
    {
        $session = (new Instance())->connect();
        $session->close();

        self::assertSame(['results' => [], 'error' => [2006, 'HY000', 'MySQL server has gone away'], 'warnings' => null, 'disconnected' => true], (new Memory())->observe($session, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')));
    }

    /**
     * The driver-visible prefix does not erase the logical completion held by the reply.
     */
    public function testRepliesStopsAtTheUnannouncedCompletionWithoutChangingLogicalResults(): void
    {
        $result = new ResultSet([], [], moreResults: false);
        $replies = [new Batch([$result, new Completion()])];
        $observer = new Memory();

        self::assertSame(['results' => [['columns' => [], 'rows' => []]]], $observer->replies($replies));
        self::assertTrue($observer->disrupted($observer->flatten($replies)));
        self::assertFalse($observer->disrupted([$result]));
    }

    /**
     * Stored markers select preparation without creating outer bound arguments.
     */
    public function testObservePreparesAnEventWithoutTreatingItsStoredMarkersAsOuterBindings(): void
    {
        $session = (new Instance('9.1.0', [], ['d']))->connect();
        $body = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter())]));
        $event = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('e'), new \SqlSemantics\Statement\Identifier\Name('d')), new \SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['2099-01-01'])), $body);
        $parameters = (new \Fuzz\Structure\Audit\Comparison())->parameters($event, $session);
        self::assertSame([], $parameters);
        $result = (new Memory())->observe($session, $event, $parameters, true);

        self::assertSame(['results' => [['affected' => 0, 'lastInsertId' => '0']], 'warnings' => [], 'disconnected' => false], $result);
    }

    /**
     * A deliberately raised SQL condition retains its error and stored diagnostics.
     */
    public function testObserveRetainsTheErrorAndDiagnosticsWithoutReadingSessionText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'this is not SQL';
        $node = new Signal(new SqlState(new Text('45000')));
        $actual = (new Memory())->observe($session, $node);

        self::assertSame(['results' => [], 'error' => [1644, '45000', 'Unhandled user-defined exception condition'], 'warnings' => [['Error', '1644', 'Unhandled user-defined exception condition']], 'disconnected' => false], $actual);
        self::assertSame('this is not SQL', $session->text);
    }

    /**
     * Failure does not erase the results already returned by a stored program.
     */
    public function testRepliesKeepsEarlierResultsAndCompletionsBeforeFailure(): void
    {
        $error = StatementError::NotSupportedYet->error('example');
        $replies = [new Batch([new ResultSet([], [[]]), new Completion(2, 3)]), $error];

        self::assertSame(['results' => [['columns' => [], 'rows' => [[]]], ['affected' => 2, 'lastInsertId' => '3']], 'error' => [1235, '42000', $error->getMessage()]], (new Memory())->replies($replies));
    }

    /**
     * A buffered client receives the error instead of the unfinished rowset, keeping earlier completed rowsets.
     */
    public function testRepliesModelsBufferingOfAnIncompleteResult(): void
    {
        $error = StatementError::NotSupportedYet->error('example');
        $replies = [new ResultSet([], [[]]), new ResultSet([], [[]], complete: false), $error];

        self::assertSame(['results' => [['columns' => [], 'rows' => [[]]]], 'error' => [1235, '42000', $error->getMessage()]], (new Memory())->replies($replies));
    }

    /**
     * The comparison includes origin, type, flags, size and character set.
     */
    public function testColumnPreservesAllComparedProtocolMetadata(): void
    {
        $column = new ResultColumn('a', Field::Long, 11, 0, 32897, 63, 'original', 't', 'base', 'd');

        self::assertSame(['name' => 'a', 'orgname' => 'original', 'table' => 't', 'orgtable' => 'base', 'db' => 'd', 'charsetnr' => 63, 'length' => 11, 'type' => 3, 'flags' => 32897, 'decimals' => 0], (new Memory())->column($column));
    }

    /**
     * BIT observations use unsigned decimal text while binary strings keep every byte.
     */
    public function testValueConvertsOnlyBitFieldsAsMysqliDoes(): void
    {
        $observer = new Memory();
        $bit = new ResultColumn('b', Field::Bit, 64, 0, 32, 63);
        $binary = new ResultColumn('b', Field::VarString, 8, 0, 128, 63);

        self::assertSame(['0', '1', '9223372036854775808', '18446744073709551615', null], array_map(static fn (?string $value): ?string => $observer->value($value, $bit), ["\0", "\x01", "\x80\0\0\0\0\0\0\0", str_repeat("\xFF", 8), null]));
        self::assertSame("\0\xFF", $observer->value("\0\xFF", $binary));
    }

    /**
     * An inaccessible condition query remains a distinct, exactly compared outcome.
     */
    public function testObserveRetainsTheFailureOfItsOwnConditionQuery(): void
    {
        $session = (new Instance())->connect();
        $session->passwordExpired = true;
        $actual = (new Memory())->observe($session, new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings());
        $error = [1820, 'HY000', 'You must reset your password using ALTER USER statement before executing this statement.'];

        self::assertSame(['results' => [], 'error' => $error, 'warnings' => null, 'disconnected' => false, 'warningsError' => $error], $actual);
        self::assertSame([['Error', 1820, $error[2]], ['Error', 1820, $error[2]]], $session->diagnostics->conditions);
    }

}
