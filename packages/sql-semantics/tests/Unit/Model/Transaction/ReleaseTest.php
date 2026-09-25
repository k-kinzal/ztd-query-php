<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Transaction\CommitTransactionStatement;
use SqlSemantics\Model\Statement\Transaction\RollbackTransactionStatement;
use SqlSemantics\Model\Transaction\Release;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Release::class)]
#[Medium]
final class ReleaseTest extends TestCase
{
    public function testRepresentsEveryReleaseChoice(): void
    {
        self::assertSame(['default', 'release', 'no-release'], array_column(Release::cases(), 'value'));
    }

    #[TestWith(['COMMIT', Release::Default])]
    #[TestWith(['COMMIT RELEASE', Release::Release])]
    #[TestWith(['COMMIT AND CHAIN NO RELEASE', Release::NoRelease])]
    public function testClassifiesTheConnectionReleaseOfACommit(string $sql, Release $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CommitTransactionStatement::class, $statement);
        self::assertSame($release, $statement->release);
        self::assertSame($sql, $statement->toString());
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
    }

    public function testClassifiesTheConnectionReleaseOfARollback(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ROLLBACK AND NO CHAIN RELEASE');
        self::assertInstanceOf(RollbackTransactionStatement::class, $statement);
        self::assertSame(Release::Release, $statement->release);
    }
}
