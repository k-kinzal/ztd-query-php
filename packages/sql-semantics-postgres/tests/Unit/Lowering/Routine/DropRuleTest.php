<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\DropRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TypeReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DropRule::class)]
#[Small]
final class DropRuleTest extends TestCase
{
    public function testDropLowersEachForm(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP INDEX CONCURRENTLY IF EXISTS i; DROP ROUTINE IF EXISTS r CASCADE; DROP OPERATOR CLASS IF EXISTS c USING btree');
        $rule = new DropRule($lowering);
        $index = $rule->drop($tree->find('DropStmt')[0]);
        $routine = $rule->drop($tree->find('RemoveFuncStmt')[0]);
        $class = $rule->drop($tree->find('DropOpClassStmt')[0]);
        self::assertSame([true, true, ObjectKind::Routine, ObjectKind::OperatorClass], [$index->concurrently, $index->ifExists, $routine->kind, $class->kind]);
    }

    public function testSignedLowersAggregates(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP AGGREGATE a(*), b(*)');
        self::assertCount(2, (new DropRule($lowering))->signed($lowering->productions->form($tree->find('RemoveAggrStmt')[0]), ObjectKind::Aggregate, 2));
    }

    public function testGenericLowersMembersOfATable(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP TRIGGER IF EXISTS g ON s.t');
        $drop = (new DropRule($lowering))->generic($lowering->productions->form($tree->find('DropStmt')[0]), true, null);
        self::assertSame([ObjectKind::Trigger, 1], [$drop->kind, count($drop->objects)]);
    }

    public function testUnqualifiedWrapsEachName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1');
        self::assertEquals([new UnqualifiedName(new Name('a'))], (new DropRule($lowering))->unqualified([new Name('a')]));
    }

    public function testTypesWrapsEachType(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP TYPE a, b');
        self::assertContainsOnlyInstancesOf(TypeReference::class, (new DropRule($lowering))->types($tree->find('type_name_list')[0]));
    }
}
