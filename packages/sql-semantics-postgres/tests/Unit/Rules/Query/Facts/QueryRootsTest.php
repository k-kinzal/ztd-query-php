<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Facts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots::class)]
#[Medium]
final class QueryRootsTest extends TestCase
{
    public function testDeriveDeclaresTheTableOfSelectInto(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT a, b INTO n FROM t', [$t, $u]);
        self::assertSame([null, 'n', 2], [$query->facts->output, $query->declarations()[0]->name->name->value, count($query->declarations()[0]->columns)]);
    }

    public function testFirstFindsTheFirstSelection(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('(SELECT 1 AS a INTO n) UNION SELECT 2');
        $operation = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class, $operation);
        self::assertSame('n', (new \SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots())->first($operation)?->into?->table->name->value);
    }

    public function testTableDeclaresAColumnOfADependentTypeWithTheMissingInputs(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT x INTO n FROM v');
        self::assertTrue($query->declarations()[0]->complete);
        $type = $query->declarations()[0]->columns[0]->type;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Undetermined::class, $type);
        self::assertSame('the declaration of relation v', $type->missing[0]->describe());
    }

    public function testTableIsIncompleteAtAnOpenRow(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 AS a, * INTO n FROM v');
        self::assertFalse($query->declarations()[0]->complete);
        self::assertCount(1, $query->declarations()[0]->columns);
    }
}
