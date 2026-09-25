<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Rule\RuleEvent;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule\CreateEmptyRuleStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RuleEvent::class)]
#[Medium]
final class RuleEventTest extends TestCase
{
    #[TestWith([RuleEvent::Insert])]
    #[TestWith([RuleEvent::Update])]
    #[TestWith([RuleEvent::Delete])]
    public function testEachWriteEventSurvivesBindingAndSerialization(RuleEvent $event): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('CREATE RULE r AS ON ' . $event->value . ' TO t DO NOTHING');
        self::assertInstanceOf(CreateEmptyRuleStatement::class, $statement);
        self::assertSame($event, $statement->event);
        self::assertSame('CREATE RULE "r" AS ON ' . $event->value . ' TO "public"."t" DO ALSO NOTHING', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
