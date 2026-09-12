<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\Upsert\ExpressionBinder;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\QualifiedColumn::class)]
#[CoversClass(ExpressionBinder::class)]
final class ExpressionBinderTest extends TestCase
{
    public function testBindExpression(): void
    {
        $binder = new ExpressionBinder(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter(), \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        self::assertSame('`__ztd_existing`.`score` + `__ztd_incoming`.`score` + `__ztd_incoming`.`score` + (SELECT score FROM t) + other.score', $binder->bindExpression('t.score + VALUES(score) + new.score + (SELECT score FROM t) + other.score', 't', ['score'], incomingNamespace: 'new'));
        self::assertSame('ABS(`custom`.`score`)', $binder->bindExpression('ABS(score)', 't', ['score'], 'custom'));
    }

    public function testSubqueryTokenIndexes(): void
    {
        $binder = new ExpressionBinder(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter(), \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('id + (SELECT score FROM t)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame([3 => true, 4 => true, 5 => true, 6 => true], $binder->subqueryTokenIndexes($tokens));
    }

    public function testIdentifier(): void
    {
        $binder = new ExpressionBinder(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter(), \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('`a``b` +', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame('a`b', $binder->identifier($tokens[0]));
        self::assertSame('+', $binder->identifier($tokens[1]));
    }

    public function testBindingAt(): void
    {
        $binder = new ExpressionBinder(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter(), \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('score + 1', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame(['offset' => 0, 'length' => 5, 'value' => '`old`.`score`'], $binder->bindingAt($tokens, 0, ['score' => true], [], 't', 'old'));
        self::assertNull($binder->bindingAt($tokens, 0, [], [], 't', 'old'));
    }

    public function testIncomingBinding(): void
    {
        $binder = new ExpressionBinder(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter(), \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('VALUES(score)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame(['offset' => 0, 'length' => 13, 'value' => '`__ztd_incoming`.`score`', 'skip' => 7], $binder->incomingBinding($tokens, 0));
        self::assertNull($binder->incomingBinding(\ZtdQuery\Sql\SqlTokenStream::tokenize('VALUES(1 + 2)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 0));
    }

    public function testQualifiedBinding(): void
    {
        $binder = new ExpressionBinder(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter(), \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('t.score', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame(['offset' => 0, 'length' => 7, 'value' => '`old`.`score`'], $binder->qualifiedBinding($tokens[0], $tokens[2], 'old'));
    }

}
