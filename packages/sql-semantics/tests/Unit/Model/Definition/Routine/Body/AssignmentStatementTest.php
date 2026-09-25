<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\AssignedUserVariable;
use SqlSemantics\Model\Definition\Routine\Body\AssignmentStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalAssignment;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AssignmentStatement::class)]
#[Medium]
final class AssignmentStatementTest extends TestCase
{
    public function testKeepsOrdinarySettingsBetweenLocalAssignments(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p(a INT) SET a = 1, @b = a, GLOBAL max_connections = a');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(AssignmentStatement::class, $statement->body);
        self::assertInstanceOf(LocalAssignment::class, $statement->body->assignments[0]);
        self::assertInstanceOf(AssignedUserVariable::class, $statement->body->assignments[1]);
        self::assertInstanceOf(AssignedSetting::class, $statement->body->assignments[2]);
        self::assertSame('CREATE PROCEDURE `p`(IN `a` integer) SET `a` = 1, @`b` = `a`, GLOBAL `max_connections` = `a`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRequiresALocalOrTriggerTarget(): void
    {
        $set = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @b = 1');
        self::assertInstanceOf(SetStatement::class, $set);
        $this->expectException(InvalidStructure::class);
        new AssignmentStatement($set->settings);
    }
}
