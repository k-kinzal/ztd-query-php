<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\OperatorSet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\OperatorMember;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(OperatorMember::class)]
final class OperatorMemberTest extends TestCase
{
    public function testKeepsTheStrategyOperatorAndPurpose(): void
    {
        $member = new OperatorMember(3, new QualifiedName(['s', '=']), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'bigint'), new QualifiedName(['f']));
        self::assertSame(3, $member->strategy);
        self::assertSame('bigint', $member->right?->name);
        self::assertSame(['f'], $member->orderFamily?->parts);
    }

    public function testRejectsANameThatIsNotAnOperator(): void
    {
        $this->expectException(InvalidStructure::class);
        new OperatorMember(1, new QualifiedName(['lt']));
    }
}
