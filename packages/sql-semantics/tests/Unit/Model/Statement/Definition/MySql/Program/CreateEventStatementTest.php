<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Stored\EventCompletion;
use SqlSemantics\Model\Definition\Routine\Stored\EventStatus;
use SqlSemantics\Model\Definition\Routine\Stored\OneTimeSchedule;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateEventStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateEventStatement::class)]
#[Medium]
final class CreateEventStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTheSameEventOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind("CREATE EVENT IF NOT EXISTS app.cleanup ON SCHEDULE EVERY 1 DAY STARTS '2030-01-01 00:00:00' ON COMPLETION PRESERVE DISABLE ON SLAVE COMMENT 'nightly' DO DELETE FROM t WHERE n < 0");
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertTrue($statement->ifNotExists);
        self::assertSame(EventCompletion::Preserve, $statement->completion);
        self::assertSame(EventStatus::DisabledOnReplica, $statement->status);
        self::assertSame("CREATE EVENT IF NOT EXISTS `app`.`cleanup` ON SCHEDULE EVERY 1 DAY STARTS '2030-01-01 00:00:00' ON COMPLETION PRESERVE DISABLE ON SLAVE COMMENT 'nightly' DO DELETE FROM `t` WHERE (`n` < 0)", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginPreservesTheDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameReplacesOnlyTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertSame('CREATE EVENT `f` ON SCHEDULE AT(CURRENT_TIMESTAMP) DO DO 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['f']))));
    }

    public function testWithScheduleReplacesTheSchedule(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE EVENT e ON SCHEDULE EVERY 1 HOUR DO DO 1');
        $other = $binder->bind("CREATE EVENT e ON SCHEDULE AT '2030-01-01' DO DO 1");
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertInstanceOf(CreateEventStatement::class, $other);
        self::assertInstanceOf(OneTimeSchedule::class, $other->schedule);
        self::assertSame("CREATE EVENT `e` ON SCHEDULE AT '2030-01-01' DO DO 1", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withSchedule($other->schedule)));
    }

    public function testWithBodyReplacesTheBody(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 1 HOUR DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertSame('CREATE EVENT `e` ON SCHEDULE EVERY 1 HOUR DO BEGIN END', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withBody(new BlockStatement(null))));
    }

    public function testWithStatusReplacesTheStatus(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 1 HOUR DISABLE DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertSame('CREATE EVENT `e` ON SCHEDULE EVERY 1 HOUR DO DO 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withStatus(EventStatus::Enabled)));
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 1 HOUR DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateEventStatement(new Origin('s0', $statement->source, Dialect::Sqlite), $statement->name, $statement->schedule, $statement->body);
    }
}
