<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalVariable;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(LocalVariable::class)]
final class LocalVariableTest extends TestCase
{
    public function testRetainsNameAndDomain(): void
    {
        $domain = new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer'));
        $variable = new LocalVariable('total', $domain);
        self::assertSame('total', $variable->name);
        self::assertSame($domain, $variable->domain);
    }

    public function testRequiresAName(): void
    {
        $this->expectException(InvalidStructure::class);
        new LocalVariable('', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')));
    }
}
