<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use Tests\Fake\MemoryClient;

/**
 * A terminal SQL condition is observed completely rather than discarded or classified as a crash.
 */
#[CoversNothing]
#[Medium]
final class ObservationProperties extends TestCase
{
    /**
     * Direct execution retains numeric information that text protocol cannot express.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesObservePreservesNonFiniteDirectCoordinatesInBothProtocols(): void
    {
        $literal = new \SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal, '000000000101000000000000000000f87f0000000000000000');
        $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('ST_X'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument($literal)]);
        $session = (new Instance('5.7.44'))->connect();
        $session->text = 'opaque non-SQL request label';
        self::assertSame([['0']], (new MemoryClient())->observe($session, $call)['results'][0]['rows']);
        self::assertSame([['NAN']], (new MemoryClient())->observe($session, $call, prepared: true)['results'][0]['rows']);
        self::assertSame('opaque non-SQL request label', $session->text);
    }

    /**
     * Direct execution observes a closed connection before resolving another structure.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesObserveRejectsFurtherDirectStructuresAfterTheConnectionHasClosed(): void
    {
        $session = (new Instance())->connect();
        $session->close();

        self::assertSame(['results' => [], 'error' => [2006, 'HY000', 'MySQL server has gone away'], 'warnings' => null, 'disconnected' => true], (new MemoryClient())->observe($session, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')));
    }

    /**
     * The driver-visible prefix does not erase the logical completion held by the reply.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesRepliesStopsAtTheUnannouncedCompletionWithoutChangingLogicalResults(): void
    {
        $result = new ResultSet([], [], moreResults: false);
        $replies = [new Batch([$result, new Completion()])];
        $observer = new MemoryClient();

        self::assertSame(['results' => [['columns' => [], 'rows' => []]]], $observer->replies($replies));
        self::assertTrue($observer->disrupted($observer->flatten($replies)));
        self::assertFalse($observer->disrupted([$result]));
    }

    /**
     * Stored markers select preparation without creating outer bound arguments.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesObservePreparesAnEventWithoutTreatingItsStoredMarkersAsOuterBindings(): void
    {
        $session = (new Instance('9.1.0', [], ['d']))->connect();
        $body = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter())]));
        $event = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('e'), new \SqlSemantics\Statement\Identifier\Name('d')), new \SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['2099-01-01'])), $body);
        $operation = (new MemoryClient())->operation($session, $event, (new \Tests\Generator\Bindings())->nulls($event));
        self::assertSame([], \SqlSemantics\Platform\MySql\Statement\Variable\ParameterInference::infer($operation));
        $result = (new MemoryClient())->observe($session, $event, [], true);

        self::assertSame(['results' => [['affected' => 0, 'lastInsertId' => '0']], 'warnings' => [], 'disconnected' => false], $result);
    }

    /**
     * A deliberately raised SQL condition retains its error and stored diagnostics.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesObserveRetainsTheErrorAndDiagnosticsWithoutReadingSessionText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'this is not SQL';
        $node = new Signal(new SqlState(new Text('45000')));
        $actual = (new MemoryClient())->observe($session, $node);

        self::assertSame(['results' => [], 'error' => [1644, '45000', 'Unhandled user-defined exception condition'], 'warnings' => [['Error', '1644', 'Unhandled user-defined exception condition']], 'disconnected' => false], $actual);
        self::assertSame('this is not SQL', $session->text);
    }

    /**
     * Failure does not erase the results already returned by a stored program.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesRepliesKeepsEarlierResultsAndCompletionsBeforeFailure(): void
    {
        $error = StatementError::NotSupportedYet->error('example');
        $replies = [new Batch([new ResultSet([], [[]]), new Completion(2, 3)]), $error];

        self::assertSame(['results' => [['columns' => [], 'rows' => [[]]], ['affected' => 2, 'lastInsertId' => '3']], 'error' => [1235, '42000', $error->getMessage()]], (new MemoryClient())->replies($replies));
    }

    /**
     * A buffered client receives the error instead of the unfinished rowset, keeping earlier completed rowsets.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesRepliesModelsBufferingOfAnIncompleteResult(): void
    {
        $error = StatementError::NotSupportedYet->error('example');
        $replies = [new ResultSet([], [[]]), new ResultSet([], [[]], complete: false), $error];

        self::assertSame(['results' => [['columns' => [], 'rows' => [[]]]], 'error' => [1235, '42000', $error->getMessage()]], (new MemoryClient())->replies($replies));
    }

    /**
     * The comparison includes origin, type, flags, size and character set.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesColumnPreservesAllComparedProtocolMetadata(): void
    {
        $column = new ResultColumn('a', Field::Long, 11, 0, 32897, 63, 'original', 't', 'base', 'd');

        self::assertSame(['name' => 'a', 'orgname' => 'original', 'table' => 't', 'orgtable' => 'base', 'db' => 'd', 'charsetnr' => 63, 'length' => 11, 'type' => 3, 'flags' => 32897, 'decimals' => 0], (new MemoryClient())->column($column));
    }

    /**
     * BIT observations use unsigned decimal text while binary strings keep every byte.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesValueConvertsOnlyBitFieldsAsMysqliDoes(): void
    {
        $observer = new MemoryClient();
        $bit = new ResultColumn('b', Field::Bit, 64, 0, 32, 63);
        $binary = new ResultColumn('b', Field::VarString, 8, 0, 128, 63);

        self::assertSame(['0', '1', '9223372036854775808', '18446744073709551615', null], array_map(static fn (?string $value): ?string => $observer->value($value, $bit), ["\0", "\x01", "\x80\0\0\0\0\0\0\0", str_repeat("\xFF", 8), null]));
        self::assertSame("\0\xFF", $observer->value("\0\xFF", $binary));
    }

    /**
     * An inaccessible condition query remains a distinct, exactly compared outcome.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesObserveRetainsTheFailureOfItsOwnConditionQuery(): void
    {
        $session = (new Instance())->connect();
        $session->passwordExpired = true;
        $actual = (new MemoryClient())->observe($session, new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings());
        $error = [1820, 'HY000', 'You must reset your password using ALTER USER statement before executing this statement.'];

        self::assertSame(['results' => [], 'error' => $error, 'warnings' => null, 'disconnected' => false, 'warningsError' => $error], $actual);
        self::assertSame([['Error', 1820, $error[2]], ['Error', 1820, $error[2]]], $session->diagnostics->conditions);
    }

}
