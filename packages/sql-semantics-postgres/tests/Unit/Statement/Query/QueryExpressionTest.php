<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression::class)]
#[Small]
final class QueryExpressionTest extends TestCase
{
    public function testOutputNameIsThatOfTheBody(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('VALUES (1) ORDER BY 1');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression::class, $statement);
        self::assertSame('column1', $statement->outputName()?->value);
    }

    public function testDeriveStatementRecordsTheRowsOfTheBody(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 AS a UNION SELECT 2 ORDER BY a');
        self::assertSame('a', $query->field(0)->name?->value);
    }

    public function testDeriveQueryHandsOrderByToTheSelectionInParentheses(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('(SELECT a FROM t) ORDER BY b', [$t, $u]);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveQueryReportsASecondOrderBy(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('(SELECT 1 ORDER BY 1) ORDER BY 1');
        self::assertSame('multiple ORDER BY clauses not allowed', $query->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheBodyAndTheOptions(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('(SELECT 1) FOR READ ONLY OFFSET 2');
        self::assertSame('(SELECT 1) FOR READ ONLY OFFSET 2', $query->toString());
    }
}
