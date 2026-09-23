<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Spatial\Organization;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(Organization::class)]
#[Medium]
final class OrganizationTest extends TestCase
{
    public function testOrganizationPairsItsNameAndIdentifier(): void
    {
        $name = Expression::literal('EPSG', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $name);
        $authority = new Organization($name, 0);
        self::assertSame($name, $authority->name);
        self::assertSame(0, $authority->identifier);
    }

    #[TestWith([-1])]
    #[TestWith([4294967296])]
    public function testOrganizationRejectsIdentifiersOutsideTheUnsigned32BitDomain(int $identifier): void
    {
        $name = Expression::literal('EPSG', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $name);
        $this->expectException(InvalidStructure::class);
        new Organization($name, $identifier);
    }

}
