<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(RoutineTarget::class)]
#[Medium]
final class RoutineTargetTest extends TestCase
{
    public function testKeepsADatabaseQualifiedRoutine(): void
    {
        $target = new RoutineTarget(new QualifiedName(['app', 'f']), RoutineKind::Function);
        self::assertSame(['app', 'f'], $target->name->parts);
        self::assertSame(RoutineKind::Function, $target->kind);
    }

    public function testRejectsAThreePartName(): void
    {
        $this->expectException(InvalidStructure::class);
        new RoutineTarget(new QualifiedName(['a', 'b', 'c']), RoutineKind::Procedure);
    }
}
