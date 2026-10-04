<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources::class)]
#[Medium]
final class SourcesTest extends TestCase
{
    public function testStatementAcceptsTheQueriesOfSelectStmt(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('SELECT 1 UNION SELECT 2');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class, $statement);
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources())->statement($statement));
    }

    public function testStatementRefusesAModification(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DELETE FROM t', [$t, $u]);
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, $statement);
        self::assertFalse((new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources())->statement($statement));
    }

    public function testInsertableRefusesValuesInParentheses(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('VALUES (1) ORDER BY 1');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression::class, $statement);
        $other = $semantics->analyze('((VALUES (1)))')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery::class, $other);
        self::assertSame([true, false], [(new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources())->insertable($statement), (new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources())->insertable($other)]);
    }
}
