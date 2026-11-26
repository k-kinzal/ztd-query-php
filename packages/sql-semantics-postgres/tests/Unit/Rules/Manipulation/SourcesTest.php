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
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $query = $semantics->inspect('SELECT 1 UNION SELECT 2');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class, $statement);
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources())->statement($statement));
    }

    public function testStatementRefusesAModification(): void
    {
        $profile = new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $query = $semantics->inspect('DELETE FROM t', [$t, $u]);
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, $statement);
        self::assertFalse((new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources())->statement($statement));
    }

    public function testInsertableRefusesValuesInParentheses(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $query = $semantics->inspect('VALUES (1) ORDER BY 1');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression::class, $statement);
        $other = $semantics->inspect('((VALUES (1)))')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery::class, $other);
        self::assertSame([true, false], [(new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources())->insertable($statement), (new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources())->insertable($other)]);
    }
}
