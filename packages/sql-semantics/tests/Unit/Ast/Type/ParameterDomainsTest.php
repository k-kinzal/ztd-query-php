<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Modifier\IdentifierParameter;

#[CoversClass(\SqlSemantics\Ast\Type\ParameterDomains::class)]
#[Medium]
final class ParameterDomainsTest extends TestCase
{
    #[TestWith(['numeric(10, 2, 3)'])]
    #[TestWith(['bit(1, 2)'])]
    #[TestWith(['varbit(1, 2)'])]
    #[TestWith(['timetz(1, 2)'])]
    #[TestWith(['int4(1)'])]
    #[TestWith(['float8(1)'])]
    #[TestWith(['text(1)'])]
    public function testArityDiagnosesEveryExtraBuiltInOperand(string $declaration): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('number of modifier operands');
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (x ' . $declaration . ')');
    }

    public function testNumberRejectsAnIdentifierAtANumericOnlyBoundary(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('SELECT 1');
        $this->expectException(\SqlSemantics\InvalidSql::class);
        \SqlSemantics\Ast\Type\ParameterDomains::number(new IdentifierParameter('width'), $source);
    }

    public function testNumberRetainsTheLiteralWithoutEvaluatingItsSpelling(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('SELECT 1');
        $number = new NumericParameter('00012');
        self::assertSame($number, \SqlSemantics\Ast\Type\ParameterDomains::number($number, $source));
        self::assertNull(\SqlSemantics\Ast\Type\ParameterDomains::number(null, $source));
    }

}
