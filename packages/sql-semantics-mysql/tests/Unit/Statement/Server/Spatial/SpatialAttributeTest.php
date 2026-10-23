<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute;

#[CoversClass(SpatialAttribute::class)]
#[Medium]
final class SpatialAttributeTest extends TestCase
{
    public function testRenderWritesTheOrganizationIdentifier(): void
    {
        self::assertSame("CREATE SPATIAL REFERENCE SYSTEM 9 NAME 'n' DEFINITION 'd' ORGANIZATION 'o' IDENTIFIED BY 9 DESCRIPTION 'x'", (new Semantics(Dialect::MySql))->analyze("create spatial reference system 9 name 'n' definition 'd' organization 'o' identified by 9 description 'x'")->toString());
    }
    public function testValueRejectsRadixStrings(): void
    {
        $this->expectExceptionMessage('A spatial reference attribute is quoted text.');

        new SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('ab', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
    /**
     * @return iterable<string, array{\SqlSemantics\Platform\MySql\Statement\Literal\Numeral}>
     */
    public static function providerNonIntegerTokens(): iterable
    {
        foreach (['1.5', '1e2', '18446744073709551616'] as $number) {
            yield $number => [new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral($number)];
        }
    }

    #[DataProvider('providerNonIntegerTokens')]
    public function testIdentifierRequiresAnIntegerToken(\SqlSemantics\Platform\MySql\Statement\Literal\Numeral $number): void
    {
        $this->expectExceptionMessage('A spatial reference identifier is an integer token.');

        new SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Organization, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('organization'), $number);
    }

}
