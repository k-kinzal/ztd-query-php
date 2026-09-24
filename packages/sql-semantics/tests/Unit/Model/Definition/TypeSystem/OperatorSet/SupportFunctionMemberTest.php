<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\OperatorSet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\SupportFunctionMember;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(SupportFunctionMember::class)]
final class SupportFunctionMemberTest extends TestCase
{
    public function testKeepsTheNumberAndFunction(): void
    {
        $member = new SupportFunctionMember(2, new RoutineByName(new QualifiedName(['f'])));
        self::assertSame(2, $member->number);
        self::assertNull($member->left);
    }

    public function testRejectsOneSidedTypes(): void
    {
        $this->expectException(InvalidStructure::class);
        new SupportFunctionMember(1, new RoutineByName(new QualifiedName(['f'])), null, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'));
    }
}
