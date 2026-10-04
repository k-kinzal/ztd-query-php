<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule::class)]
#[Medium]
final class ColumnRuleTest extends TestCase
{
    public function testElementsLowersEveryElement(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, LIKE u, CHECK (a > 0))');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->elements($tree->find('OptTableElementList')[0]);
        self::assertSame([
          0 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Element\\ColumnDefinition',
          1 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Element\\LikeClause',
          2 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Constraint\\Table\\TableCheck',
        ], array_map(static fn ($element): string => $element::class, $value));
    }

    public function testTypedElementsLowersColumnOptions(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t OF ty (a NOT NULL)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->typedElements($tree->find('OptTypedTableElementList')[0]);
        $n1 = $value[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnOptions::class, $n1);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Element\\ColumnOptions', $n1::class);
    }

    public function testColumnLowersTheDefinition(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a text STORAGE plain)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->column($tree->find('columnDef')[0]);
        self::assertSame('plain', $value->storage?->method?->value);
    }

    public function testOptionsDropsWithOptions(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t OF ty (a WITH OPTIONS NOT NULL)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->options($tree->find('columnOptions')[0]);
        self::assertSame(1, count($value->qualifiers));
    }

    public function testStorageIsNullForDefault(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a text STORAGE DEFAULT)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->storage($tree->find('column_storage')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnStorage::class, $n1);
        self::assertSame(null, $n1->method);
    }

    public function testCompressionLowersTheMethod(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a text COMPRESSION lz4)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->compression($tree->find('opt_column_compression')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnCompression::class, $n1);
        self::assertSame('lz4', $n1->method?->value);
    }

    public function testQualifiersLowersEveryQualifier(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int NOT NULL DEFAULT 1)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->qualifiers($tree->find('ColQualList')[0]);
        self::assertSame(2, count($value));
    }

    public function testConstraintLowersAReference(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int REFERENCES u (b) MATCH FULL)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->constraint($lowering->productions->form($tree->find('ColConstraintElem')[0]), null);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\References::class, $n1);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\KeyMatch::Full, $n1->match);
    }

    public function testWhenLowersByDefault(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int GENERATED BY DEFAULT AS IDENTITY)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->when($tree->find('generated_when')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\GeneratedWhen::ByDefault, $value);
    }

    public function testAttributeLowersInitiallyDeferred(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int UNIQUE INITIALLY DEFERRED)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->attribute($tree->find('ConstraintAttr')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::InitiallyDeferred, $value);
    }

    public function testNoInheritIsTrueWhenWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int CHECK (a > 0) NO INHERIT)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->noInherit($tree->find('opt_no_inherit')[0]);
        self::assertSame(true, $value);
    }

    public function testLikeKeepsTheOptionsInOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (LIKE u INCLUDING ALL EXCLUDING INDEXES)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\ColumnRule($lowering))->like($tree->find('TableLikeClause')[0]);
        self::assertSame([
          0 => 'ALL',
          1 => 'INDEXES',
        ], array_map(static fn ($option): string => $option->kind->value, $value->options));
    }
}
