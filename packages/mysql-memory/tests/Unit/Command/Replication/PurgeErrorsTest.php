<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\PurgeErrors;
use MySqlMemory\Error\Family\QueryError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo;

#[CoversClass(PurgeErrors::class)]
#[Small]
final class PurgeErrorsTest extends TestCase
{
    public function testPreparedRetainsTheFirstErrorAndAddsTheArgumentCondition(): void
    {
        $original = QueryError::BadField->error('missing', 'field list');
        $error = (new PurgeErrors())->prepared($original, new PurgeLogsBefore(new NumberLiteral('1')));

        self::assertSame(1054, $error->getCode());
        self::assertSame($original->getMessage(), $error->getMessage());
        self::assertSame($original->sqlState(), $error->sqlState());
        self::assertSame([[1210, 'Incorrect arguments to PURGE LOGS BEFORE']], $error->following);
    }

    public function testPreparedDistinguishesAnUnresolvedRoutineDatabase(): void
    {
        $error = \MySqlMemory\Error\Family\ProgramError::RoutineMissing->error('FUNCTION', 'missing');
        $statement = new PurgeLogsBefore(new NumberLiteral('1'));
        $prepared = (new PurgeErrors())->prepared($error, $statement, true);

        self::assertSame($error, (new PurgeErrors())->prepared($error, $statement));
        self::assertSame([[1210, 'Incorrect arguments to PURGE LOGS BEFORE']], $prepared->following);
        self::assertSame($prepared, (new PurgeErrors())->prepared($prepared, $statement, true));
    }

    public function testPreparedLeavesEarlierDatabaseChecksAndOtherCommandsAlone(): void
    {
        $database = QueryError::NoDatabase->error();
        $missing = QueryError::BadField->error('missing', 'field list');

        self::assertSame($database, (new PurgeErrors())->prepared($database, new PurgeLogsBefore(new NumberLiteral('1'))));
        self::assertSame($missing, (new PurgeErrors())->prepared($missing, new PurgeLogsTo(new Text('log'))));
    }
}
