<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;

#[CoversClass(ReplicationVocabulary::class)]
#[Medium]
final class ReplicationVocabularyTest extends TestCase
{
    #[TestWith([ReplicationVocabulary::Current, 'Source_Host', 'Source_Host'])]
    #[TestWith([ReplicationVocabulary::Legacy, 'Source_Host', 'Master_Host'])]
    #[TestWith([ReplicationVocabulary::Legacy, 'Replica_IO_Running', 'Slave_IO_Running'])]
    #[TestWith([ReplicationVocabulary::Legacy, 'Seconds_Behind_Source', 'Seconds_Behind_Master'])]
    #[TestWith([ReplicationVocabulary::Legacy, 'Last_Errno', 'Last_Errno'])]
    #[TestWith([ReplicationVocabulary::Legacy, 'Replicate_Do_DB', 'Replicate_Do_DB'])]
    public function testLabelRewritesOnlyTheReplicationTermsOfTheCurrentVocabulary(ReplicationVocabulary $vocabulary, string $current, string $expected): void
    {
        self::assertSame($expected, $vocabulary->label($current));
    }

    #[TestWith([ReplicationVocabulary::Current, 'mysql-5.7.44', false])]
    #[TestWith([ReplicationVocabulary::Current, 'mysql-8.0.44', true])]
    #[TestWith([ReplicationVocabulary::Legacy, 'mysql-8.3.0', true])]
    #[TestWith([ReplicationVocabulary::Legacy, 'mysql-8.4.7', false])]
    public function testSpelledInFollowsTheReleaseVocabulary(ReplicationVocabulary $vocabulary, string $version, bool $expected): void
    {
        self::assertSame($expected, $vocabulary->spelledIn($version));
    }
}
