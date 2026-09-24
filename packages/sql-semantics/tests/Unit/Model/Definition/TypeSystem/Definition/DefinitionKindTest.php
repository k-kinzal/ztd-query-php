<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DefinitionKind::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DefinitionKindTest extends TestCase
{
    public function testAcceptsTheValueFormOfEachKind(): void
    {
        self::assertTrue(DefinitionKind::Name->accepts(new QualifiedName(['s', 'f'])));
        self::assertFalse(DefinitionKind::Name->accepts('f'));
        self::assertTrue(DefinitionKind::Operator->accepts(new QualifiedName(['s', '<->'])));
        self::assertFalse(DefinitionKind::Operator->accepts(new QualifiedName(['foo'])));
        self::assertFalse(DefinitionKind::Operator->accepts(new QualifiedName(['a', 'b', '+'])));
        self::assertTrue(DefinitionKind::Type->accepts(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')));
        self::assertFalse(DefinitionKind::Type->accepts(TypeDescriptor::builtin(Dialect::MySql, 'integer')));
        self::assertTrue(DefinitionKind::Boolean->accepts(false));
        self::assertTrue(DefinitionKind::Integer->accepts(-7));
        self::assertTrue(DefinitionKind::Length->accepts(-2));
        self::assertFalse(DefinitionKind::Length->accepts(0));
        self::assertFalse(DefinitionKind::Length->accepts(32768));
        self::assertTrue(DefinitionKind::Text->accepts(''));
        self::assertTrue(DefinitionKind::Choice->accepts('safe'));
        self::assertFalse(DefinitionKind::Choice->accepts(1));
    }
}
