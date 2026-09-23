<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Characteristics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineAlteration;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(RoutineAlteration::class)]
#[Medium]
final class RoutineAlterationTest extends TestCase
{
    public function testOmittedChangesRemainIndependentOfDeclaredDefaults(): void
    {
        $changes = new RoutineAlteration(security: RoutineSecurity::Definer);
        self::assertNull($changes->language);
        self::assertNull($changes->dataAccess);
        self::assertNull($changes->comment);
        self::assertSame(RoutineSecurity::Definer, $changes->security);
    }

    public function testLanguageRequiresANonemptyIdentifier(): void
    {
        $this->expectException(InvalidStructure::class);
        new RoutineAlteration(language: '');
    }

    #[TestWith([Dialect::MySql, 1])]
    #[TestWith([Dialect::PostgreSql, 'note'])]
    public function testCommentRequiresMySqlText(Dialect $dialect, int|string $value): void
    {
        $comment = Expression::literal($value, $dialect);
        self::assertInstanceOf(Literal::class, $comment);
        $this->expectException(InvalidStructure::class);
        new RoutineAlteration(comment: $comment);
    }
}
