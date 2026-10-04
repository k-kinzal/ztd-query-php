<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Targets::class)]
#[Medium]
final class TargetsTest extends TestCase
{
    public function testResolveAnswersTheDeclaredTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('SELECT 1', $context);
        $derivation = new \SqlSemantics\Construction\Derivation($statement->context);
        $value = (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Targets())->resolve($derivation, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $n1 = $value->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $n1);
        self::assertSame(true, $n1->table === $context[0]);
    }

    public function testShapeHasASlotPerColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('SELECT 1', $context);
        $derivation = new \SqlSemantics\Construction\Derivation($statement->context);
        self::assertSame(2, count((new \SqlSemantics\Platform\PostgreSql\Rules\Table\Targets())->shape($context[0])->slots));
    }

    public function testImplicitAnswersTheSystemColumns(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('SELECT 1', $context);
        $derivation = new \SqlSemantics\Construction\Derivation($statement->context);
        self::assertSame(6, count((new \SqlSemantics\Platform\PostgreSql\Rules\Table\Targets())->implicit((new \SqlSemantics\Platform\PostgreSql\Rules\Table\Targets())->resolve($derivation, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))))));
    }

    public function testScopeMakesTheTableTheOnlyVisibleRelation(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE INDEX ON t (a, ctid)', $context);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }
}
