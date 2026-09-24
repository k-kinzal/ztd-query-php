<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Replication\ReplicaStatusField;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;
use SqlSemantics\Type\Nullability;

#[CoversClass(ReplicaStatusField::class)]
#[Medium]
final class ReplicaStatusFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Replica_IO_State', 'Source_Host'], array_slice(array_map(static fn (ReplicaStatusField $field): string => $field->label(), ReplicaStatusField::cases()), 0, 2));
    }

    #[TestWith([ReplicaStatusField::SecondsBehindSource, 'bigint'])]
    #[TestWith([ReplicaStatusField::SourceHost, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(ReplicaStatusField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityMarksLagAndRemainingDelayAsAbsentWhileIdle(): void
    {
        self::assertSame(Nullability::NotNull, ReplicaStatusField::IoState->nullability());
        self::assertSame(Nullability::MaybeNull, ReplicaStatusField::SecondsBehindSource->nullability());
        self::assertSame(Nullability::MaybeNull, ReplicaStatusField::SqlRemainingDelay->nullability());
    }

    #[TestWith([ReplicaStatusField::ReplicateDoDb, 'Replicate_Do_DB'])]
    #[TestWith([ReplicaStatusField::SecondsBehindSource, 'Seconds_Behind_Master'])]
    #[TestWith([ReplicaStatusField::GetSourcePublicKey, 'Get_master_public_key'])]
    public function testLabelInRewritesOnlyReplicationTermsForTheLegacyVocabulary(ReplicaStatusField $field, string $expected): void
    {
        self::assertSame($expected, $field->labelIn(ReplicationVocabulary::Legacy));
        self::assertSame($field->value, $field->labelIn(ReplicationVocabulary::Current));
    }

    #[TestWith(['mysql-5.6.51', 'Auto_Position'])]
    #[TestWith(['mysql-5.7.44', 'Source_TLS_Version'])]
    #[TestWith(['mysql-8.0.44', 'Network_Namespace'])]
    #[TestWith([null, 'Network_Namespace'])]
    public function testReportedEndsWithTheLastFieldOfTheRelease(?string $version, string $last): void
    {
        $fields = ReplicaStatusField::reported($version);
        self::assertSame($last, $fields[count($fields) - 1]->value);
        self::assertSame(ReplicaStatusField::IoState, $fields[0]);
    }

    public function testTypeDeclaresEveryCounterAsBigint(): void
    {
        $counters = [ReplicaStatusField::SourcePort, ReplicaStatusField::ConnectRetry, ReplicaStatusField::ReadSourceLogPosition, ReplicaStatusField::RelayLogPosition, ReplicaStatusField::LastErrorNumber, ReplicaStatusField::SkipCounter, ReplicaStatusField::ExecSourceLogPosition, ReplicaStatusField::RelayLogSpace, ReplicaStatusField::UntilLogPosition, ReplicaStatusField::SecondsBehindSource, ReplicaStatusField::LastIoErrorNumber, ReplicaStatusField::LastSqlErrorNumber, ReplicaStatusField::SourceServerId, ReplicaStatusField::SqlDelay, ReplicaStatusField::SqlRemainingDelay, ReplicaStatusField::SourceRetryCount, ReplicaStatusField::AutoPosition, ReplicaStatusField::GetSourcePublicKey];
        self::assertSame(array_fill(0, count($counters), 'bigint'), array_map(static fn (ReplicaStatusField $field): string => $field->type(), $counters));
        self::assertSame(['varchar'], array_values(array_unique(array_map(static fn (ReplicaStatusField $field): string => $field->type(), array_values(array_filter(ReplicaStatusField::cases(), static fn (ReplicaStatusField $field): bool => !in_array($field, $counters, true)))))));
    }
}
