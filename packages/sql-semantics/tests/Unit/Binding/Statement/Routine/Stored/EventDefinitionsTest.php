<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
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
}
