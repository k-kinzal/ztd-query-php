<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class)]
#[Small]
final class SetOperationTest extends TestCase
{
    public function testOutputNameIsThatOfTheFirstOperand(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 AS a UNION SELECT 2 AS b');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class, $statement);
        self::assertSame('a', $statement->outputName()?->value);
    }

    public function testDeriveStatementRecordsTheCombinedRows(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 UNION SELECT NULL');
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::Nullable, $query->field(0)->nullability);
    }

    public function testDeriveQueryResolvesTheCommonType(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT a FROM t UNION SELECT a FROM u', [$t, $u]);
        self::assertSame('bigint', ($query->field(0)->type instanceof \SqlSemantics\Statement\Type\Known ? $query->field(0)->type->descriptor->name() : null));
    }

    public function testRenderKeepsTheGrouping(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 EXCEPT DISTINCT SELECT 2 INTERSECT SELECT 3');
        self::assertSame('SELECT 1 EXCEPT DISTINCT SELECT 2 INTERSECT SELECT 3', $query->toString());
    }
}
