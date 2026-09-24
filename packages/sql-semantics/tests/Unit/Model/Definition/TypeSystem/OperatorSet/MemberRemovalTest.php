<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\OperatorSet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\MemberKind;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\MemberRemoval;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(MemberRemoval::class)]
final class MemberRemovalTest extends TestCase
{
    public function testKeepsTheKindNumberAndTypes(): void
    {
        $removal = new MemberRemoval(MemberKind::Operator, 4, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
        self::assertSame(MemberKind::Operator, $removal->kind);
        self::assertSame('text', $removal->right->name);
    }

    public function testRejectsNumberZero(): void
    {
        $this->expectException(InvalidStructure::class);
        new MemberRemoval(MemberKind::Function, 0, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'));
    }
}
