<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Xml\XmlInvariant;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(XmlInvariant::class)]
final class XmlInvariantTest extends TestCase
{
    public function testCheckAcceptsPostgreSqlOperandsAndTheResultType(): void
    {
        $facts = new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::NotNull);
        XmlInvariant::check('XMLCONCAT', $facts, 'xml', [Expression::literal('<a/>', Dialect::PostgreSql)]);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('XMLCONCAT requires PostgreSQL operands.');
        XmlInvariant::check('XMLCONCAT', $facts, 'xml', [Expression::literal('<a/>', Dialect::MySql)]);
    }

    public function testCheckRejectsAnotherResultType(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('XMLPARSE is a PostgreSQL expression of type xml.');
        XmlInvariant::check('XMLPARSE', new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), Nullability::NotNull), 'xml', []);
    }
}
