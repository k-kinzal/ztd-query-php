<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule::class)]
#[Medium]
final class ConstraintRuleTest extends TestCase
{
    public function testTableConstraintKeepsTheName(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, CONSTRAINT k PRIMARY KEY (a))');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->tableConstraint($tree->find('TableConstraint')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey::class, $n1);
        self::assertSame('k', $n1->name?->value);
    }

    public function testElementLowersAForeignKey(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, FOREIGN KEY (a) REFERENCES u)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->element($lowering->productions->form($tree->find('ConstraintElem')[0]), null);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Constraint\\Table\\ForeignKey', $value::class);
    }

    public function testIncludedLowersTheColumns(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, UNIQUE (a) INCLUDE (b))');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->included($tree->find('opt_c_include')[0]);
        self::assertSame('b', $value[0]->value);
    }

    public function testExclusionsLowersTheOperators(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, EXCLUDE (a WITH =, a WITH OPERATOR(pg_catalog.<>)))');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->exclusions($tree->find('ExclusionConstraintList')[0]);
        self::assertSame([
          0 => false,
          1 => true,
        ], array_map(static fn ($element): bool => $element->operator->explicit, $value));
    }

    public function testTablespaceLowersTheIndexTablespace(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int UNIQUE USING INDEX TABLESPACE s)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->tablespace($tree->find('OptConsTableSpace')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Statement\Identifier\Name::class, $n1);
        self::assertSame('s', $n1->value);
    }

    public function testIndexLowersTheExistingIndex(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t ADD UNIQUE USING INDEX i');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->index($tree->find('ExistingIndex')[0]);
        self::assertSame('i', $value->value);
    }

    public function testAttributesKeepsTheOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, UNIQUE (a) INITIALLY DEFERRED DEFERRABLE)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->attributes($tree->find('ConstraintAttributeSpec')[0]);
        self::assertSame([
          0 =>
          \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::InitiallyDeferred,
          1 =>
          \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::Deferrable,
        ], $value);
    }

    public function testMatchIsNullWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int REFERENCES u)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->match($tree->find('key_match')[0]);
        self::assertSame(null, $value);
    }

    public function testActionsKeepsTheOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int REFERENCES u ON DELETE CASCADE ON UPDATE RESTRICT)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->actions($tree->find('key_actions')[0]);
        self::assertSame([
          0 => 'DELETE',
          1 => 'UPDATE',
        ], array_map(static fn ($action): string => $action->event->value, $value));
    }

    public function testActionLowersTheColumnsOfSetNull(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int REFERENCES u ON DELETE SET NULL (a))');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ConstraintRule($lowering))->action(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent::Delete, $tree->find('key_action')[0]);
        self::assertSame('a', $value->columns[0]->value);
    }
}
