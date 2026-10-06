<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Operators;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Operators::class)]
#[Small]
final class OperatorsTest extends TestCase
{
    public function testSymbolReadsNotEqualsAsItsOperator(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        self::assertSame('<>', $lowering->operators->symbol(new Token(1, 'NOT_EQUALS', '!=', 0))->value);
        self::assertSame('@>', $lowering->operators->symbol(new Token(1, 'Op', '@>', 0))->value);
        self::assertCount(2, $lowering->leaves->all());
    }

    public function testOperatorLowersTheOperatorSyntax(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 USING OPERATOR(pg_catalog.<)');
        $operator = $lowering->operators->operator($tree->find('qual_all_Op')[0]);
        self::assertSame(['<', 'pg_catalog', true], [$operator->name->value, $operator->qualifiers[0]->value, $operator->explicit]);
    }

    public function testOperatorLowersABareOperator(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 USING >=');
        self::assertSame('>=', $lowering->operators->operator($tree->find('qual_all_Op')[0])->name->value);
    }

    public function testQualifiedLowersTheQualifierChain(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP OPERATOR a.b.+ (int, int)');
        $operator = $lowering->operators->qualified($tree->find('any_operator')[0], false);
        self::assertSame(['a', 'b'], array_map(static fn (Name $name): string => $name->value, $operator->qualifiers));
        self::assertFalse($operator->explicit);
    }
}
