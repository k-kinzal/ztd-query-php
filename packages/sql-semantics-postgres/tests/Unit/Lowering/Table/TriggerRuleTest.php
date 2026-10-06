<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule::class)]
#[Medium]
final class TriggerRuleTest extends TestCase
{
    public function testTriggerLowersAConstraintTrigger(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE CONSTRAINT TRIGGER g AFTER INSERT ON t FOR EACH ROW EXECUTE FUNCTION f()');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->trigger($tree->find('CreateTrigStmt')[0]);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Trigger\\CreateConstraintTrigger', get_debug_type($value));
    }

    public function testTimingLowersInsteadOf(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TRIGGER g INSTEAD OF INSERT ON v FOR EACH ROW EXECUTE FUNCTION f()');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->timing($tree->find('TriggerActionTime')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerTiming::InsteadOf, $value);
    }

    public function testEventsKeepsTheOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TRIGGER g AFTER DELETE OR UPDATE OF a ON t EXECUTE FUNCTION f()');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->events($tree->find('TriggerEvents')[0]);
        self::assertSame([
          0 => 'DELETE',
          1 => 'UPDATE',
        ], array_map(static fn ($event): string => $event->kind->value, $value));
    }

    public function testTransitionsLowersTheNames(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TRIGGER g AFTER INSERT ON t REFERENCING NEW TABLE AS n EXECUTE FUNCTION f()');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->transitions($tree->find('TriggerReferencing')[0]);
        self::assertSame('n', $value[0]->name->value);
    }

    public function testLevelIsFalseForStatement(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TRIGGER g AFTER INSERT ON t FOR STATEMENT EXECUTE FUNCTION f()');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->level($tree->find('TriggerForSpec')[0]);
        self::assertSame(false, $value);
    }

    public function testWhenIsNullWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TRIGGER g AFTER INSERT ON t EXECUTE FUNCTION f()');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->when($tree->find('TriggerWhen')[0]);
        self::assertSame(null, $value);
    }

    public function testArgumentsLowersEveryArgument(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TRIGGER g AFTER INSERT ON t EXECUTE FUNCTION f(1, \'x\')');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->arguments($tree->find('TriggerFuncArgs')[0]);
        self::assertSame(2, count($value));
    }

    public function testEventTriggerLowersTheEvent(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE EVENT TRIGGER e ON sql_drop EXECUTE FUNCTION f()');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->eventTrigger($tree->find('CreateEventTrigStmt')[0]);
        self::assertSame('sql_drop', $value->event->value);
    }

    public function testFiltersKeepsTheValuesInOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE EVENT TRIGGER e ON sql_drop WHEN tag IN (\'a\', \'b\') EXECUTE FUNCTION f()');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->filters($tree->find('event_trigger_when_list')[0]);
        self::assertSame([
          0 => 'a',
          1 => 'b',
        ], array_map(static fn ($item): string => $item->value, $value[0]->values));
    }

    public function testAlterEventTriggerLowersTheState(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER EVENT TRIGGER e DISABLE');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->alterEventTrigger($tree->find('AlterEventTrigStmt')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\FiringState::Disable, $value->state);
    }

    public function testNoiseRefusesAnotherProduction(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TRIGGER g AFTER INSERT ON t EXECUTE FUNCTION f()');
        $this->expectExceptionMessage('No semantic rule is implemented for: TriggerWhen:');
        (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\TriggerRule($lowering))->noise($tree->find('TriggerWhen')[0], 'TriggerWhen: WHEN ( a_expr )');
    }
}
