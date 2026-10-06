<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class)]
#[Medium]
final class OutputPositionTest extends TestCase
{
    public function testValueIsTheSignedPosition(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 ORDER BY - (2)');
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $position = $select->options?->order[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class, $position);
        self::assertSame('-2', $position->value());
    }

    public function testDeriveScalarDenotesTheField(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT a, b FROM t ORDER BY 2', [$t, $u]);
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $position = $select->options?->order[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class, $position);
        $resolution = $query->facts->scalar($position)->resolution;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\AliasTarget::class, $resolution);
        self::assertSame($query->field(1), $resolution->field);
    }

    public function testDeriveScalarDependsOnAnUnexpandedStar(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT * FROM v ORDER BY 3');
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $position = $select->options?->order[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class, $position);
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Dependent::class, $query->facts->scalar($position)->type);
    }

    public function testDeriveScalarReportsAPositionPastTheList(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 ORDER BY 0');
        self::assertSame('ORDER BY position 0 is not in select list', $query->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheConstant(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 GROUP BY (1)');
        self::assertSame('SELECT 1 GROUP BY (1)', $query->toString());
    }
}
