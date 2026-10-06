<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\SequenceRule::class)]
#[Medium]
final class SequenceRuleTest extends TestCase
{
    public function testCreateLowersTheOptions(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE SEQUENCE IF NOT EXISTS s CYCLE');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\SequenceRule($lowering))->create($tree->find('CreateSeqStmt')[0]);
        self::assertSame([
          0 => true,
          1 => 1,
        ], [$value->ifNotExists, count($value->options)]);
    }

    public function testAlterLowersIfExists(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER SEQUENCE IF EXISTS s CYCLE');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\SequenceRule($lowering))->alter($tree->find('AlterSeqStmt')[0]);
        self::assertSame(true, $value->ifExists);
    }

    public function testParenthesizedIsEmptyWithoutParentheses(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int GENERATED ALWAYS AS IDENTITY)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\SequenceRule($lowering))->parenthesized($tree->find('OptParenthesizedSeqOptList')[0]);
        self::assertSame([
        ], $value);
    }

    public function testOptionalIsEmptyWithoutOptions(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE SEQUENCE s');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\SequenceRule($lowering))->optional($tree->find('OptSeqOptList')[0]);
        self::assertSame([
        ], $value);
    }

    public function testOptionsKeepsTheOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE SEQUENCE s START 1 CACHE 2');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\SequenceRule($lowering))->options($tree->find('SeqOptList')[0]);
        self::assertSame([
          0 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Sequence\\SequenceNumber',
          1 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Sequence\\SequenceNumber',
        ], array_map(static fn ($option): string => $option::class, $value));
    }

    public function testOptionLowersAsAType(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE SEQUENCE s AS bigint');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\SequenceRule($lowering))->option($tree->find('SeqOptElem')[0]);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Sequence\\SequenceAs', get_debug_type($value));
    }

    public function testNoiseRefusesAnotherProduction(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE SEQUENCE s CYCLE');
        $this->expectExceptionMessage('No semantic rule is implemented for: SeqOptElem: CYCLE');
        (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\SequenceRule($lowering))->noise($tree->find('SeqOptElem')[0], 'opt_by: BY');
    }
}
