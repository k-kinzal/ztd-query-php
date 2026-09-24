<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplicationRelease::class)]
#[Medium]
final class ReplicationReleaseTest extends TestCase
{
    public function testNumberOrdersReleaseTagsAndTreatsAMissingTagAsNewest(): void
    {
        self::assertSame(50651, ReplicationRelease::number('mysql-5.6.51'));
        self::assertSame(80044, ReplicationRelease::number('mysql-8.0.44'));
        self::assertSame(PHP_INT_MAX, ReplicationRelease::number(null));
    }

    public function testOfReadsTheReleaseOfTheBindingContext(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("PURGE MASTER LOGS TO 'a'");
        self::assertSame(50744, ReplicationRelease::of($statement->origin));
        self::assertNull(ReplicationRelease::of(new Origin('s0', $statement->source, Dialect::MySql)));
    }

    public function testLegacyIdentifiesReleasesBeforeTheReplicaVocabulary(): void
    {
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("PURGE MASTER LOGS TO 'a'");
        $current = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind("PURGE MASTER LOGS TO 'a'");
        self::assertTrue(ReplicationRelease::legacy($legacy->origin));
        self::assertFalse(ReplicationRelease::legacy($current->origin));
    }

    public function testRequireRejectsAReleaseOutsideTheBounds(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("PURGE MASTER LOGS TO 'a'");
        ReplicationRelease::require($statement->origin, 'form', 50700);
        $this->expectException(InvalidStructure::class);
        ReplicationRelease::require($statement->origin, 'form', 80000);
    }

    public function testRequireRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'a'");
        $this->expectException(InvalidStructure::class);
        ReplicationRelease::require(new Origin('s0', $statement->source, Dialect::PostgreSql), 'form');
    }

    #[TestWith(['xmysql-5.6.51'])]
    #[TestWith(['mysql-5.6.51x'])]
    #[TestWith(['postgresql'])]
    public function testNumberTreatsAnotherTagAsNewest(string $tag): void
    {
        self::assertSame(PHP_INT_MAX, ReplicationRelease::number($tag));
    }

    public function testRequireAcceptsBothBoundsThemselves(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("PURGE MASTER LOGS TO 'a'");
        ReplicationRelease::require($statement->origin, 'form', 50744, 50744);
        self::assertSame(50744, ReplicationRelease::of($statement->origin));
    }

    public function testRequireNamesTheFormOutsideTheBounds(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("PURGE MASTER LOGS TO 'a'");
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('RESET form is not available in this MySQL release.');
        ReplicationRelease::require($statement->origin, 'RESET form', 0, 50743);
    }

    public function testRequireNamesTheFormOfAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'a'");
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('RESET form requires MySQL.');
        ReplicationRelease::require(new Origin('s0', $statement->source, Dialect::PostgreSql), 'RESET form');
    }
}
