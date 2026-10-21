<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\PartitionInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused;

#[CoversClass(PartitionInput::class)]
#[Small]
final class PartitionInputTest extends TestCase
{
    public function testDefinitionKeepsTriviaAfterAnOmittedSubpartitionList(): void
    {
        $session = (new Instance())->connect();
        $prefix = 'ALTER TABLE d.t ADD PARTITION (PARTITION p0 (SUBPARTITION s0), PARTITION p1';
        $failure = new RejectedInput(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::SubpartitionCount, strlen($prefix));
        $error = (new PartitionInput())->definition($failure, $prefix . "\n/*gap*/ )", $session);

        self::assertSame("Wrong number of subpartitions defined, mismatch with previous setting near '\n/*gap*/ )' at line 1", $error->getMessage());
    }

    public function testDefinitionReadsTheFollowingCommaOnLegacyReleases(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $prefix = 'ALTER TABLE d.t REORGANIZE PARTITION p INTO (PARTITION p0 (SUBPARTITION s0), PARTITION p1';
        $failure = new RejectedInput(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::SubpartitionCount, strlen($prefix));
        $error = (new PartitionInput())->definition($failure, $prefix . "\n/*gap*/ , PARTITION p2)", $session);

        self::assertSame("Wrong number of subpartitions defined, mismatch with previous setting near ' PARTITION p2)' at line 2", $error->getMessage());
    }

    public function testRefusedKeepsEarlierCompletedConditionsBeforeAMalformedBody(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $prefix = "CREATE PROCEDURE d.p() BEGIN SELECT DATE 'bad'; PARTITION";
        $failure = new RejectedInput(new PartitionEntryRefused(), strlen($prefix));

        $error = (new PartitionInput())->refused($failure, $prefix . ' BROKEN; END', $session);

        self::assertSame(1525, $error->getCode());
        self::assertSame([['Error', 1525, "Incorrect DATE value: 'bad'"]], $session->diagnostics->conditions);
    }

    public function testErrorQuotesTheRemainingClientInputAtItsOriginalLine(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->following = "\nSELECT 2";
        $sql = "/* before\n comment */\nPARTITION BY HASH(a);";
        $failure = new RejectedInput(new PartitionEntryRefused(), strlen("/* before\n comment */\nPARTITION"));

        $error = (new PartitionInput())->error($failure, $sql, $session);

        self::assertSame(1064, $error->getCode());
        self::assertSame('42000', $error->sqlState());
        self::assertSame("Partitioning can not be used stand-alone in query near 'PARTITION BY HASH(a);\nSELECT 2' at line 3", $error->getMessage());
    }
    public function testDefinitionPreservesTheNoDatabaseConditionBeforeItsBody(): void
    {
        $session = (new Instance())->connect();
        $sql = 'ALTER TABLE t ADD PARTITION (PARTITION p0, PARTITION p1 (SUBPARTITION s1))';
        $failure = new RejectedInput(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::SubpartitionCount, (int) strpos($sql, 'SUBPARTITION s1'));
        self::assertSame(1046, (new PartitionInput())->definition($failure, $sql, $session)->getCode());
    }

}
