<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class)]
#[Medium]
final class SetOperationTest extends TestCase
{
    public function testOutputNameIsThatOfTheFirstOperand(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->inspect('SELECT 1 AS a UNION SELECT 2 AS b');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class, $statement);
        self::assertSame('a', $statement->outputName()?->value);
    }

    public function testDeriveStatementRecordsTheCombinedRows(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->inspect('SELECT 1 UNION SELECT NULL');
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::Nullable, $query->field(0)->nullability);
    }

    public function testDeriveQueryResolvesTheCommonType(): void
    {
        $profile = new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->inspect('SELECT a FROM t UNION SELECT a FROM u', [$t, $u]);
        self::assertSame('bigint', ($query->field(0)->type instanceof \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known ? $query->field(0)->type->descriptor->name() : null));
    }

    public function testRenderKeepsTheGrouping(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->inspect('SELECT 1 EXCEPT DISTINCT SELECT 2 INTERSECT SELECT 3');
        self::assertSame('SELECT 1 EXCEPT DISTINCT SELECT 2 INTERSECT SELECT 3', $query->toString());
    }
}
