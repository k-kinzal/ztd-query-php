<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\EventRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;

#[CoversClass(EventRule::class)]
#[Medium]
final class EventRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCreateLowersAnEventOfEveryLayout(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerCreateLowersAnEventOfEveryLayout')]
    public function testCreateLowersAnEventOfEveryLayout(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze("create definer = current_user event if not exists db.e on schedule every 1 hour starts '2020-01-01' ends '2021-01-01' on completion not preserve disable comment 'c' do select 1");
        $statement = $create->statement;
        self::assertInstanceOf(CreateEvent::class, $statement);
        self::assertSame('e', $statement->name->name->value);
        self::assertSame('db', $statement->name->schema?->value);
        self::assertInstanceOf(RecurringSchedule::class, $statement->schedule);
        self::assertInstanceOf(Select::class, $statement->body);
        self::assertSame(Completion::NotPreserve, $statement->completion);
        self::assertSame(EventStatus::Disable, $statement->status);
        self::assertSame('c', $statement->comment?->value);
        self::assertNotNull($statement->definer);
        self::assertTrue($statement->ifNotExists);
        self::assertSame("CREATE DEFINER = CURRENT_USER EVENT IF NOT EXISTS db.e ON SCHEDULE EVERY 1 HOUR STARTS '2020-01-01' ENDS '2021-01-01' ON COMPLETION NOT PRESERVE DISABLE COMMENT 'c' DO SELECT 1", $create->toString());
    }

    public function testCreateLeavesTheOptionalClausesAbsent(): void
    {
        $create = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("create event e on schedule at '2020-01-01' do begin select 1; end");
        $statement = $create->statement;
        self::assertInstanceOf(CreateEvent::class, $statement);
        self::assertInstanceOf(OnceSchedule::class, $statement->schedule);
        self::assertInstanceOf(Block::class, $statement->body);
        self::assertNull($statement->completion);
        self::assertNull($statement->status);
        self::assertNull($statement->comment);
        self::assertNull($statement->definer);
        self::assertFalse($statement->ifNotExists);
        self::assertSame("CREATE EVENT e ON SCHEDULE AT '2020-01-01' DO BEGIN SELECT 1; END", $create->toString());
    }

    public function testCreateRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new EventRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->create(new Node('sp_name', 1, []), null);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerAlterLowersEveryChange(): iterable
    {
        yield 'everything on 5.6' => ['mysql-5.6.51', "alter definer = root@localhost event e on schedule every 1 day on completion preserve rename to db.e2 disable comment 'x' do select 2", "ALTER DEFINER = root@localhost EVENT e ON SCHEDULE EVERY 1 DAY ON COMPLETION PRESERVE RENAME TO db.e2 DISABLE COMMENT 'x' DO SELECT 2"];
        yield 'completion on 5.7' => ['mysql-5.7.44', 'alter event e on completion not preserve', 'ALTER EVENT e ON COMPLETION NOT PRESERVE'];
        yield 'schedule on 5.7' => ['mysql-5.7.44', "alter event e on schedule at '2020-01-01'", "ALTER EVENT e ON SCHEDULE AT '2020-01-01'"];
        yield 'schedule and completion on 8.0' => ['mysql-8.0.44', "alter event e on schedule every 1 day starts '2020-01-01' on completion preserve rename to e2 enable", "ALTER EVENT e ON SCHEDULE EVERY 1 DAY STARTS '2020-01-01' ON COMPLETION PRESERVE RENAME TO e2 ENABLE"];
        yield 'schedule on 9.1' => ['mysql-9.1.0', "alter event e on schedule at '2020-01-01' disable on replica do select 1", "ALTER EVENT e ON SCHEDULE AT '2020-01-01' DISABLE ON REPLICA DO SELECT 1"];
        yield 'rename on 9.1' => ['mysql-9.1.0', 'alter event e rename to e2', 'ALTER EVENT e RENAME TO e2'];
    }

    #[DataProvider('providerAlterLowersEveryChange')]
    public function testAlterLowersEveryChange(string $release, string $sql, string $expected): void
    {
        $alter = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        self::assertInstanceOf(AlterEvent::class, $alter->statement);
        self::assertSame('e', $alter->statement->name->name->value);
        self::assertSame($expected, $alter->toString());
    }

    public function testAlterLowersTheStructure(): void
    {
        $alter = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("alter definer = root@localhost event e on schedule every 1 day on completion preserve rename to db.e2 disable comment 'x' do select 2");
        $statement = $alter->statement;
        self::assertInstanceOf(AlterEvent::class, $statement);
        self::assertInstanceOf(RecurringSchedule::class, $statement->schedule);
        self::assertSame(Completion::Preserve, $statement->completion);
        self::assertSame('e2', $statement->newName?->name->value);
        self::assertSame('db', $statement->newName->schema?->value);
        self::assertSame(EventStatus::Disable, $statement->status);
        self::assertSame('x', $statement->comment?->value);
        self::assertInstanceOf(Select::class, $statement->body);
        self::assertNotNull($statement->definer);

        $bare = (new Semantics(Dialect::MySql))->analyze('alter event e on completion preserve')->statement;
        self::assertInstanceOf(AlterEvent::class, $bare);
        self::assertNull($bare->schedule);
        self::assertSame(Completion::Preserve, $bare->completion);
        self::assertNull($bare->newName);
        self::assertNull($bare->status);
        self::assertNull($bare->comment);
        self::assertNull($bare->body);
        self::assertNull($bare->definer);
    }

    public function testAlterRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new EventRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: alter_procedure_stmt: ALTER PROCEDURE_SYM sp_name sp_a_chistics');

        $rule->alter(new Form(new Node('alter_procedure_stmt', 0, []), 'alter_procedure_stmt: ALTER PROCEDURE_SYM sp_name sp_a_chistics'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerScheduleLowersAtAndEvery(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerScheduleLowersAtAndEvery')]
    public function testScheduleLowersAtAndEvery(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $once = $semantics->analyze("create event e on schedule at '2020-01-01' do select 1")->statement;
        self::assertInstanceOf(CreateEvent::class, $once);
        self::assertInstanceOf(OnceSchedule::class, $once->schedule);
        self::assertInstanceOf(StringLiteral::class, $once->schedule->at);

        $every = $semantics->analyze('create event e on schedule every 5 minute do select 1')->statement;
        self::assertInstanceOf(CreateEvent::class, $every);
        self::assertInstanceOf(RecurringSchedule::class, $every->schedule);
        self::assertInstanceOf(NumberLiteral::class, $every->schedule->quantity);
        self::assertSame(IntervalUnit::Minute, $every->schedule->unit);
        self::assertNull($every->schedule->starts);
        self::assertNull($every->schedule->ends);
    }

    public function testScheduleRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new EventRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->schedule(new Node('sp_name', 1, []));
    }

    public function testBoundLowersStartsAndEnds(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $starts = $semantics->analyze("create event e on schedule every 1 day starts '2020-01-01' do select 1");
        self::assertInstanceOf(CreateEvent::class, $starts->statement);
        self::assertInstanceOf(RecurringSchedule::class, $starts->statement->schedule);
        self::assertInstanceOf(StringLiteral::class, $starts->statement->schedule->starts);
        self::assertNull($starts->statement->schedule->ends);
        self::assertSame("CREATE EVENT e ON SCHEDULE EVERY 1 DAY STARTS '2020-01-01' DO SELECT 1", $starts->toString());

        $ends = $semantics->analyze("create event e on schedule every 1 day ends '2021-01-01' do select 1");
        self::assertInstanceOf(CreateEvent::class, $ends->statement);
        self::assertInstanceOf(RecurringSchedule::class, $ends->statement->schedule);
        self::assertNull($ends->statement->schedule->starts);
        self::assertInstanceOf(StringLiteral::class, $ends->statement->schedule->ends);
        self::assertSame("CREATE EVENT e ON SCHEDULE EVERY 1 DAY ENDS '2021-01-01' DO SELECT 1", $ends->toString());
    }

    public function testBoundRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new EventRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->bound(new Node('sp_name', 1, []));
    }

    /**
     * @return iterable<string, array{string, string, Completion}>
     */
    public static function providerCompletionLowersPreserveAndNotPreserve(): iterable
    {
        yield 'preserve on 5.6' => ['mysql-5.6.51', 'ON COMPLETION PRESERVE', Completion::Preserve];
        yield 'not preserve on 5.6' => ['mysql-5.6.51', 'ON COMPLETION NOT PRESERVE', Completion::NotPreserve];
        yield 'preserve on 8.0' => ['mysql-8.0.44', 'ON COMPLETION PRESERVE', Completion::Preserve];
        yield 'not preserve on 9.1' => ['mysql-9.1.0', 'ON COMPLETION NOT PRESERVE', Completion::NotPreserve];
    }

    #[DataProvider('providerCompletionLowersPreserveAndNotPreserve')]
    public function testCompletionLowersPreserveAndNotPreserve(string $release, string $clause, Completion $completion): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $create = $semantics->analyze('CREATE EVENT e ON SCHEDULE AT 1 ' . $clause . ' DO SELECT 1');
        self::assertInstanceOf(CreateEvent::class, $create->statement);
        self::assertSame($completion, $create->statement->completion);
        self::assertSame('CREATE EVENT e ON SCHEDULE AT 1 ' . $clause . ' DO SELECT 1', $create->toString());

        $alter = $semantics->analyze('ALTER EVENT e ' . $clause);
        self::assertInstanceOf(AlterEvent::class, $alter->statement);
        self::assertSame($completion, $alter->statement->completion);
    }

    public function testCompletionRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new EventRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->completion(new Node('sp_name', 1, []));
    }

    /**
     * @return iterable<string, array{string, string, EventStatus|null}>
     */
    public static function providerStatusLowersEveryStatus(): iterable
    {
        yield 'absent on 5.6' => ['mysql-5.6.51', '', null];
        yield 'enable on 5.6' => ['mysql-5.6.51', ' ENABLE', EventStatus::Enable];
        yield 'disable on 5.7' => ['mysql-5.7.44', ' DISABLE', EventStatus::Disable];
        yield 'disable on slave on 5.7' => ['mysql-5.7.44', ' DISABLE ON SLAVE', EventStatus::DisableOnSlave];
        yield 'disable on slave on 8.0' => ['mysql-8.0.44', ' DISABLE ON SLAVE', EventStatus::DisableOnSlave];
        yield 'disable on replica on 8.2' => ['mysql-8.2.0', ' DISABLE ON REPLICA', EventStatus::DisableOnReplica];
        yield 'disable on slave on 9.1' => ['mysql-9.1.0', ' DISABLE ON SLAVE', EventStatus::DisableOnSlave];
        yield 'disable on replica on 9.1' => ['mysql-9.1.0', ' DISABLE ON REPLICA', EventStatus::DisableOnReplica];
    }

    #[DataProvider('providerStatusLowersEveryStatus')]
    public function testStatusLowersEveryStatus(string $release, string $clause, ?EventStatus $status): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $create = $semantics->analyze('CREATE EVENT e ON SCHEDULE AT 1' . $clause . ' DO SELECT 1');
        self::assertInstanceOf(CreateEvent::class, $create->statement);
        self::assertSame($status, $create->statement->status);
        self::assertSame('CREATE EVENT e ON SCHEDULE AT 1' . $clause . ' DO SELECT 1', $create->toString());

        $alter = $semantics->analyze('ALTER EVENT e' . $clause);
        self::assertInstanceOf(AlterEvent::class, $alter->statement);
        self::assertSame($status, $alter->statement->status);
    }

    public function testStatusRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new EventRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->status(new Node('sp_name', 1, []));
    }

    public function testCommentLowersTheText(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.0.44');
        $create = $semantics->analyze('create event e on schedule at 1 comment "it\'s" do select 1');
        self::assertInstanceOf(CreateEvent::class, $create->statement);
        self::assertSame("it's", $create->statement->comment?->value);
        self::assertSame("CREATE EVENT e ON SCHEDULE AT 1 COMMENT 'it''s' DO SELECT 1", $create->toString());

        $alter = $semantics->analyze("alter event e comment 'x'");
        self::assertInstanceOf(AlterEvent::class, $alter->statement);
        self::assertSame('x', $alter->statement->comment?->value);
    }

    public function testCommentRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new EventRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->comment(new Node('sp_name', 1, []));
    }
}
