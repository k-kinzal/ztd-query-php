<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\OperatorSet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\StorageMember;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(StorageMember::class)]
final class StorageMemberTest extends TestCase
{
    public function testKeepsThePostgreSqlType(): void
    {
        self::assertSame('text', (new StorageMember(TypeDescriptor::builtin(Dialect::PostgreSql, 'text')))->type->name);
        $this->expectException(InvalidStructure::class);
        new StorageMember(TypeDescriptor::builtin(Dialect::MySql, 'text'));
    }
}
