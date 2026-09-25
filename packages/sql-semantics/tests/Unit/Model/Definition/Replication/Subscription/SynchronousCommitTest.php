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

#[CoversClass(Operand\SynchronousCommit::class)]
#[Medium]
final class SynchronousCommitTest extends TestCase
{
    #[TestWith([Operand\SynchronousCommit::Local, 'local'])]
    #[TestWith([Operand\SynchronousCommit::RemoteWrite, "'REMOTE_WRITE'"])]
    #[TestWith([Operand\SynchronousCommit::RemoteApply, 'remote_apply'])]
    #[TestWith([Operand\SynchronousCommit::On, 'yes'])]
    #[TestWith([Operand\SynchronousCommit::On, '1'])]
    #[TestWith([Operand\SynchronousCommit::Off, "'false'"])]
    public function testEachValueSurvivesBindingAndSerialization(Operand\SynchronousCommit $value, string $spelling): void
    {
        $binder = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()));
        $statement = $binder->bind('ALTER SUBSCRIPTION s SET (synchronous_commit = ' . $spelling . ')');
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame($value, $statement->options->synchronousCommit);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
