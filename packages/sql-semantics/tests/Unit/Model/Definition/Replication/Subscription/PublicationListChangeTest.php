<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operand\PublicationListChange::class)]
#[Medium]
final class PublicationListChangeTest extends TestCase
{
    #[TestWith([Operand\PublicationListChange::Set, 'SET'])]
    #[TestWith([Operand\PublicationListChange::Add, 'ADD'])]
    #[TestWith([Operand\PublicationListChange::Drop, 'DROP'])]
    public function testEachValueSurvivesBindingAndSerialization(Operand\PublicationListChange $value, string $spelling): void
    {
        $binder = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()));
        $statement = $binder->bind('ALTER SUBSCRIPTION s ' . $spelling . ' PUBLICATION p');
        self::assertInstanceOf(Statement\AlterSubscriptionPublicationsStatement::class, $statement);
        self::assertSame($value, $statement->change);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
