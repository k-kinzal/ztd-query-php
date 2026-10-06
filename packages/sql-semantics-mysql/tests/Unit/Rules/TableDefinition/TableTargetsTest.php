<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(TableTargets::class)]
#[Medium]
final class TableTargetsTest extends TestCase
{
    public function testShapeHasOneSlotPerDeclaredColumn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT INVISIBLE)');

        self::assertCount(1, (new TableTargets())->shape($create->declarations()[0])->slots);
    }

    public function testImplicitFindsTheInvisibleColumns(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT INVISIBLE)');

        self::assertSame('b', (new TableTargets())->implicit($create->declarations()[0])[0]->names[0]->value);
    }

    public function testExistingResolvesATableName(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertInstanceOf(MissingTable::class, (new TableTargets())->existing($derivation, new QualifiedName(new Name('t')))->table);
    }

    public function testParentResolvesTheTableBeingDefined(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT)');
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = (new TableTargets())->parent($derivation, new QualifiedName(new Name('t')), $create->declarations()[0], new QualifiedName(new Name('t')));

        self::assertInstanceOf(DeclaredTable::class, $fact->table);
    }

    public function testScopeSeesTheTableOnly(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $scope = (new TableTargets())->scope($derivation, $statement, new QualifiedName(new Name('t')), (new TableTargets())->shape($create->declarations()[0]));

        self::assertCount(1, $scope->relations);
    }
}
