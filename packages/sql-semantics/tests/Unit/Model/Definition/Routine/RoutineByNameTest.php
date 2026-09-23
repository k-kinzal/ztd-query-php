<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;

#[CoversClass(Routine\RoutineByName::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RoutineByNameTest extends TestCase
{
    public function testIdentityRetainsTheIdentifierPath(): void
    {
        $name = new QualifiedName(['app', 'f']);
        $target = new Routine\RoutineByName($name);
        self::assertSame($name, $target->name);
    }

}
