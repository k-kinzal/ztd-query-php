<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet as Member;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Serialization\Definition\TypeSystem\OperatorSetMembers;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(OperatorSetMembers::class)]
final class OperatorSetMembersTest extends TestCase
{
    public function testMemberSpellsEachForm(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        self::assertSame('OPERATOR 1 < (integer, integer) FOR ORDER BY "f"', OperatorSetMembers::member(new Member\OperatorMember(1, new QualifiedName(['<']), $integer, $integer, new QualifiedName(['f'])))->toString());
        self::assertSame('FUNCTION 2 "g"', OperatorSetMembers::member(new Member\SupportFunctionMember(2, new RoutineByName(new QualifiedName(['g']))))->toString());
        self::assertSame('STORAGE integer', OperatorSetMembers::member(new Member\StorageMember($integer))->toString());
    }

    public function testRemovalSpellsBothTypes(): void
    {
        $text = TypeDescriptor::builtin(Dialect::PostgreSql, 'text');
        self::assertSame('OPERATOR 3(text, text)', OperatorSetMembers::removal(new Member\MemberRemoval(Member\MemberKind::Operator, 3, $text, $text))->toString());
    }

    public function testTypesIsEmptyWithoutTypes(): void
    {
        self::assertSame([], OperatorSetMembers::types(null, null));
    }
}
