<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable::class)]
#[Medium]
final class TargetTableTest extends TestCase
{
    public function testNameAnswersTheTableName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('UPDATE app.t SET a = 1');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update::class, $statement);
        self::assertSame('t', $statement->target->name()->name->value);
    }

    public function testAliasAnswersTheCorrelationName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DELETE FROM t x');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, $statement);
        self::assertSame('x', $statement->target->alias()?->value);
    }

    public function testDeriveRelationResolvesTheDeclaredTable(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DELETE FROM t', [$t, $u]);
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, $statement);
        $resolution = $query->facts->relation($statement->target)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $resolution);
        self::assertSame($t, $resolution->table);
    }

    public function testDeriveRelationIgnoresACommonTableOfTheSameName(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('WITH t AS (SELECT 1 AS z) DELETE FROM t', [$t, $u]);
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, $statement);
        $resolution = $query->facts->relation($statement->target)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $resolution);
        self::assertSame($t, $resolution->table);
    }

    public function testRenderWritesOnlyAndTheAlias(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('UPDATE ONLY t x SET a = 1', [$t, $u]);
        self::assertSame('UPDATE ONLY t AS x SET a = 1', $query->toString());
    }
}
