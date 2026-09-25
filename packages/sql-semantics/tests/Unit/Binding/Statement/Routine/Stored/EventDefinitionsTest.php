<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Stored\EventDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Stored\EventCompletion;
use SqlSemantics\Model\Definition\Routine\Stored\EventStatus;
use SqlSemantics\Model\Definition\Routine\Stored\OneTimeSchedule;
use SqlSemantics\Model\Definition\Routine\Stored\RecurringSchedule;
use SqlSemantics\Model\Statement\Definition\MySql\Program\AlterEventStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateEventStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EventDefinitions::class)]
#[Medium]
final class EventDefinitionsTest extends TestCase
{
    public function testCreateAppliesServerDefaults(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP DO BEGIN END');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertSame(EventCompletion::Drop, $statement->completion);
        self::assertSame(EventStatus::Enabled, $statement->status);
        self::assertNull($statement->comment);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
    }

    public function testAlterReadsOnlyTheCompletionChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER EVENT e ON COMPLETION PRESERVE');
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertSame(EventCompletion::Preserve, $statement->changes->completion);
        self::assertNull($statement->changes->schedule);
    }

    /**
     * @param class-string $class
     */
    #[TestWith(['AT CURRENT_TIMESTAMP', OneTimeSchedule::class])]
    #[TestWith(['EVERY 1 YEAR_MONTH ENDS CURRENT_TIMESTAMP', RecurringSchedule::class])]
    public function testScheduleDistinguishesOneTimeAndRecurringSchedules(string $schedule, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER EVENT e ON SCHEDULE ' . $schedule);
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertInstanceOf($class, $statement->changes->schedule);
    }

    #[TestWith(['ON COMPLETION PRESERVE', EventCompletion::Preserve])]
    #[TestWith(['ON COMPLETION NOT PRESERVE', EventCompletion::Drop])]
    public function testCompletionReadsThePolicy(string $clause, EventCompletion $completion): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER EVENT e ' . $clause);
        self::assertSame($completion, EventDefinitions::completion(Tree::outer($tree, ['ev_on_completion'])[0]));
        self::assertNull(EventDefinitions::completion(null));
    }

    #[TestWith(['ENABLE', EventStatus::Enabled])]
    #[TestWith(['DISABLE', EventStatus::Disabled])]
    #[TestWith(['DISABLE ON REPLICA', EventStatus::DisabledOnReplica])]
    public function testStatusReadsTheState(string $clause, EventStatus $status): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER EVENT e ' . $clause);
        self::assertSame($status, EventDefinitions::status(Tree::outer($tree, ['opt_ev_status'])[0]));
        self::assertNull(EventDefinitions::status(null));
    }

    public function testCommentKeepsTheLiteralSpelling(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("ALTER EVENT e COMMENT 'it''s'");
        self::assertSame("'it''s'", EventDefinitions::comment(Tree::outer($tree, ['opt_ev_comment'])[0])?->text);
        self::assertNull(EventDefinitions::comment(null));
    }

    public function testBodyBindsAnEventProgram(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER EVENT e DO BEGIN DECLARE a INT; SET a = 1; END');
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->changes->body);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerEventsSpellEveryRequestedClause(): iterable
    {
        yield 'mysql-5.6.51 0' => ['mysql-5.6.51', 'create event if not exists e on schedule every 2 day_hour starts \'2030-01-01\' ends \'2031-01-01\' on completion not preserve disable comment \'c\' do set @a = 1', 'CREATE EVENT IF NOT EXISTS `e` ON SCHEDULE EVERY 2 DAY_HOUR STARTS \'2030-01-01\' ENDS \'2031-01-01\' DISABLE COMMENT \'c\' DO SET @`a` = 1'];
        yield 'mysql-5.6.51 1' => ['mysql-5.6.51', 'alter event e on schedule at \'2030-01-01\' on completion preserve rename to f disable on slave comment \'x\' do set @a = 2', 'ALTER EVENT `e` ON SCHEDULE AT \'2030-01-01\' ON COMPLETION PRESERVE RENAME TO `f` DISABLE ON SLAVE COMMENT \'x\' DO SET @`a` = 2'];
        yield 'mysql-5.6.51 2' => ['mysql-5.6.51', 'alter event e on schedule every 1 day', 'ALTER EVENT `e` ON SCHEDULE EVERY 1 DAY'];
        yield 'mysql-5.6.51 3' => ['mysql-5.6.51', 'alter event e rename to db.f', 'ALTER EVENT `e` RENAME TO `db`.`f`'];
        yield 'mysql-5.6.51 4' => ['mysql-5.6.51', 'alter event e enable', 'ALTER EVENT `e` ENABLE'];
        yield 'mysql-5.6.51 5' => ['mysql-5.6.51', 'alter event e comment \'y\'', 'ALTER EVENT `e` COMMENT \'y\''];
        yield 'mysql-5.6.51 6' => ['mysql-5.6.51', 'alter event e on completion not preserve', 'ALTER EVENT `e` ON COMPLETION NOT PRESERVE'];
        yield 'mysql-5.6.51 7' => ['mysql-5.6.51', 'create event e on schedule every 1 second starts now() do set @a = 1', 'CREATE EVENT `e` ON SCHEDULE EVERY 1 SECOND STARTS(CURRENT_TIMESTAMP) DO SET @`a` = 1'];
        yield 'mysql-5.6.51 8' => ['mysql-5.6.51', 'create event e on schedule at now() on completion preserve do set @a = 1', 'CREATE EVENT `e` ON SCHEDULE AT(CURRENT_TIMESTAMP) ON COMPLETION PRESERVE DO SET @`a` = 1'];
        yield 'mysql-9.1.0 0' => ['mysql-9.1.0', 'create event if not exists e on schedule every 2 day_hour starts \'2030-01-01\' ends \'2031-01-01\' on completion not preserve disable comment \'c\' do set @a = 1', 'CREATE EVENT IF NOT EXISTS `e` ON SCHEDULE EVERY 2 DAY_HOUR STARTS \'2030-01-01\' ENDS \'2031-01-01\' DISABLE COMMENT \'c\' DO SET @`a` = 1'];
        yield 'mysql-9.1.0 1' => ['mysql-9.1.0', 'alter event e on schedule at \'2030-01-01\' on completion preserve rename to f disable on slave comment \'x\' do set @a = 2', 'ALTER EVENT `e` ON SCHEDULE AT \'2030-01-01\' ON COMPLETION PRESERVE RENAME TO `f` DISABLE ON SLAVE COMMENT \'x\' DO SET @`a` = 2'];
        yield 'mysql-9.1.0 2' => ['mysql-9.1.0', 'alter event e on schedule every 1 day', 'ALTER EVENT `e` ON SCHEDULE EVERY 1 DAY'];
        yield 'mysql-9.1.0 3' => ['mysql-9.1.0', 'alter event e rename to db.f', 'ALTER EVENT `e` RENAME TO `db`.`f`'];
        yield 'mysql-9.1.0 4' => ['mysql-9.1.0', 'alter event e enable', 'ALTER EVENT `e` ENABLE'];
        yield 'mysql-9.1.0 5' => ['mysql-9.1.0', 'alter event e comment \'y\'', 'ALTER EVENT `e` COMMENT \'y\''];
        yield 'mysql-9.1.0 6' => ['mysql-9.1.0', 'alter event e on completion not preserve', 'ALTER EVENT `e` ON COMPLETION NOT PRESERVE'];
        yield 'mysql-9.1.0 7' => ['mysql-9.1.0', 'create event e on schedule every 1 second starts now() do set @a = 1', 'CREATE EVENT `e` ON SCHEDULE EVERY 1 SECOND STARTS(CURRENT_TIMESTAMP) DO SET @`a` = 1'];
        yield 'mysql-9.1.0 8' => ['mysql-9.1.0', 'create event e on schedule at now() on completion preserve do set @a = 1', 'CREATE EVENT `e` ON SCHEDULE AT(CURRENT_TIMESTAMP) ON COMPLETION PRESERVE DO SET @`a` = 1'];
    }

    #[DataProvider('providerEventsSpellEveryRequestedClause')]
    public function testEventsSpellEveryRequestedClause(string $version, string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql)));
    }

    public function testScheduleRejectsAMicrosecondInterval(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 1 DAY_MICROSECOND DO SET @a = 1');
    }
}
