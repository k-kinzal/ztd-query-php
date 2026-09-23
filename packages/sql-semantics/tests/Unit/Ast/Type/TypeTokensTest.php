<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

#[CoversClass(\SqlSemantics\Ast\Type\TypeTokens::class)]
#[Medium]
final class TypeTokensTest extends TestCase
{
    public function testOuterDoesNotConfuseNestedModifierIdentifiersWithTypeWords(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t(x numeric(("UNSIGNED")))');
        $type = Tree::outer($source, ['Typename'])[0];
        self::assertSame(['numeric'], array_map(static fn ($token): string => $token->text, \SqlSemantics\Ast\Type\TypeTokens::outer($type)));
    }

    public function testNumbersRetainsUnsignedPrecisionAndScale(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('CREATE TABLE t(x DECIMAL(12, 2) UNSIGNED)');
        $type = Tree::outer($source, ['type'])[0];
        self::assertSame(['12', '2'], array_map(static fn (NumericParameter $parameter): string => $parameter->spelling, \SqlSemantics\Ast\Type\TypeTokens::numbers($type)));
    }

}
