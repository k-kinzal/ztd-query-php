<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(CreationRoles::class)]
#[Small]
final class CreationRolesTest extends TestCase
{
    public function testColumnlessDistinguishesConstraintsFromColumnsAndQueries(): void
    {
        $rules = new CreationRoles();
        self::assertTrue($rules->columnless([], null));
        self::assertTrue($rules->columnless([new M\Table\Key\CheckConstraint(new M\Literal\BooleanLiteral(true))], null));
        self::assertFalse($rules->columnless([], new M\Table\TableQuery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]))));
    }

    public function testReadProblemKeepsAContradictoryReadOutsideAnOrdinaryDefinition(): void
    {
        $query = new M\Table\TableQuery(new M\Query\Select([], [new M\Query\Star()]));
        $rules = new CreationRoles();
        self::assertNull($rules->readProblem(null));
        self::assertNotNull($rules->readProblem($query));
        self::assertTrue($rules->deferred([], [], $query));
        $this->expectExceptionMessage('A creation query with a fixed read-shape constraint belongs to a deferred instruction.');
        new M\Table\CreateTable(new QualifiedName(new Name('t')), query: $query);
    }

    public function testDeferredRetainsTheColumnlessRoleWithoutAnOrdinaryStatement(): void
    {
        $name = new QualifiedName(new Name('t'));
        $definition = new M\Routine\Program\Write\DeferredCreateTable($name, ifNotExists: true);
        self::assertTrue((new CreationRoles())->deferred($definition->elements, $definition->options, $definition->query));
        $this->expectExceptionMessage('An ordinary table creation has a declared column or a creation query.');
        new M\Table\CreateTable($name, ifNotExists: true);
    }
}
