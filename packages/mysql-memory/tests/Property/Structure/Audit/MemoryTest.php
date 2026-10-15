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
}
