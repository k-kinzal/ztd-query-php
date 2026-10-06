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
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\TriggerRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\OrderPlacement;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;

#[CoversClass(TriggerRule::class)]
#[Medium]
final class TriggerRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, TriggerTime, TriggerEvent, string}>
     */
    public static function providerCreateLowersATriggerOfEveryLayout(): iterable
    {
        yield 'after delete on 5.6' => ['mysql-5.6.51', 'create definer = root@localhost trigger db.tr after delete on db.t for each row set @a = 1', TriggerTime::After, TriggerEvent::Delete, 'CREATE DEFINER = root@localhost TRIGGER db.tr AFTER DELETE ON db.t FOR EACH ROW SET @a = 1'];
        yield 'before insert on 5.6' => ['mysql-5.6.51', 'create definer = root@localhost trigger db.tr before insert on db.t for each row set @a = 1', TriggerTime::Before, TriggerEvent::Insert, 'CREATE DEFINER = root@localhost TRIGGER db.tr BEFORE INSERT ON db.t FOR EACH ROW SET @a = 1'];
        yield 'before update on 5.7' => ['mysql-5.7.44', 'create definer = root@localhost trigger db.tr before update on db.t for each row set @a = 1', TriggerTime::Before, TriggerEvent::Update, 'CREATE DEFINER = root@localhost TRIGGER db.tr BEFORE UPDATE ON db.t FOR EACH ROW SET @a = 1'];
        yield 'after insert on 8.0' => ['mysql-8.0.44', 'create definer = root@localhost trigger db.tr after insert on db.t for each row set @a = 1', TriggerTime::After, TriggerEvent::Insert, 'CREATE DEFINER = root@localhost TRIGGER db.tr AFTER INSERT ON db.t FOR EACH ROW SET @a = 1'];
        yield 'before delete on 9.1' => ['mysql-9.1.0', 'create definer = root@localhost trigger db.tr before delete on db.t for each row set @a = 1', TriggerTime::Before, TriggerEvent::Delete, 'CREATE DEFINER = root@localhost TRIGGER db.tr BEFORE DELETE ON db.t FOR EACH ROW SET @a = 1'];
    }

    #[DataProvider('providerCreateLowersATriggerOfEveryLayout')]
    public function testCreateLowersATriggerOfEveryLayout(string $release, string $sql, TriggerTime $time, TriggerEvent $event, string $expected): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);
        self::assertSame('tr', $statement->name->name->value);
        self::assertSame('db', $statement->name->schema?->value);
        self::assertSame($time, $statement->time);
        self::assertSame($event, $statement->event);
        self::assertSame('t', $statement->table->name->name->value);
        self::assertSame('db', $statement->table->name->schema?->value);
        self::assertInstanceOf(SetVariables::class, $statement->body);
        self::assertNull($statement->order);
        self::assertNotNull($statement->definer);
        self::assertFalse($statement->ifNotExists);
        self::assertSame($expected, $create->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCreateReadsIfNotExists(): iterable
    {
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerCreateReadsIfNotExists')]
    public function testCreateReadsIfNotExists(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze('create trigger if not exists tr after insert on t for each row begin end');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);
        self::assertTrue($statement->ifNotExists);
        self::assertNull($statement->definer);
        self::assertInstanceOf(Block::class, $statement->body);
        self::assertSame('CREATE TRIGGER IF NOT EXISTS tr AFTER INSERT ON t FOR EACH ROW BEGIN END', $create->toString());
    }

    public function testCreateRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new TriggerRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->create(new Node('sp_name', 1, []), null);
    }

    /**
     * @return iterable<string, array{string, string, OrderPlacement, string}>
     */
    public static function providerOrderLowersFollowsAndPrecedes(): iterable
    {
        yield 'follows on 5.7' => ['mysql-5.7.44', 'create trigger tr before update on t for each row follows other set @a = 1', OrderPlacement::Follows, 'CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW FOLLOWS other SET @a = 1'];
        yield 'precedes a string on 8.0' => ['mysql-8.0.44', "create trigger tr after insert on t for each row precedes 'other' set @a = 1", OrderPlacement::Precedes, 'CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW PRECEDES other SET @a = 1'];
        yield 'follows on 9.1' => ['mysql-9.1.0', 'create trigger tr before delete on t for each row follows other set @a = 1', OrderPlacement::Follows, 'CREATE TRIGGER tr BEFORE DELETE ON t FOR EACH ROW FOLLOWS other SET @a = 1'];
        yield 'precedes on 9.1' => ['mysql-9.1.0', 'create trigger tr before delete on t for each row precedes other set @a = 1', OrderPlacement::Precedes, 'CREATE TRIGGER tr BEFORE DELETE ON t FOR EACH ROW PRECEDES other SET @a = 1'];
    }

    #[DataProvider('providerOrderLowersFollowsAndPrecedes')]
    public function testOrderLowersFollowsAndPrecedes(string $release, string $sql, OrderPlacement $placement, string $expected): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);
        self::assertSame($placement, $statement->order?->placement);
        self::assertSame('other', $statement->order->other->value);
        self::assertSame($expected, $create->toString());
    }

    public function testOrderAnswersNullForAnAbsentClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new TriggerRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertNull($rule->order(new Node('trigger_follows_precedes_clause', 0, [])));
    }

    public function testOrderRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new TriggerRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->order(new Node('sp_name', 1, []));
    }
}
