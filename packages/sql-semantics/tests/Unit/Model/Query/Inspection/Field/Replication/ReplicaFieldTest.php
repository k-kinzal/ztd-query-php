<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Replication\ReplicaField;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;
use SqlSemantics\Type\Nullability;

#[CoversClass(ReplicaField::class)]
#[Medium]
final class ReplicaFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Server_Id', 'Host', 'Port', 'Source_Id', 'Replica_UUID'], array_map(static fn (ReplicaField $field): string => $field->label(), ReplicaField::cases()));
    }

    #[TestWith([ReplicaField::Port, 'bigint'])]
    #[TestWith([ReplicaField::Host, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(ReplicaField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testLabelInFollowsTheRequestVocabulary(): void
    {
        self::assertSame(['Server_id', 'Host', 'Port', 'Master_id', 'Slave_UUID'], array_map(static fn (ReplicaField $field): string => $field->labelIn(ReplicationVocabulary::Legacy), ReplicaField::cases()));
        self::assertSame('Replica_UUID', ReplicaField::Uuid->labelIn(ReplicationVocabulary::Current));
    }

    public function testNullabilityDefaultsToPresent(): void
    {
        self::assertSame(Nullability::NotNull, ReplicaField::cases()[0]->nullability());
    }
}
