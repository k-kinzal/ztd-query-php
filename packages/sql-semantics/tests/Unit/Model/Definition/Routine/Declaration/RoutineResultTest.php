<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\RoutineResult;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(RoutineResult::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\Medium]
final class RoutineResultTest extends TestCase
{
    public function testRetainsTheSetRequest(): void
    {
        $result = new RoutineResult(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), true);
        self::assertTrue($result->setOf);
    }

    public function testRejectsATypeOfAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new RoutineResult(TypeDescriptor::builtin(Dialect::MySql, 'text'));
    }
}
