<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Scalar\Operator\OperatorReferences;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator;
use SqlSemantics\Model\Validation\InputViolation;

#[CoversClass(OperatorReferences::class)]
#[Medium]
final class OperatorReferencesTest extends TestCase
{
    /**
     * @param list<string> $qualifier
     */
    #[TestWith(['SELECT 1 OPERATOR(PG_CATALOG.+) 2', ['pg_catalog'], '+'])]
    #[TestWith(['SELECT 1 OPERATOR(db."Geo".<->) 2', ['db', 'Geo'], '<->'])]
    #[TestWith(['SELECT 1 OPERATOR(!=) 2', [], '<>'])]
    #[TestWith(['SELECT 1 <-> 2', [], '<->'])]
    public function testReadSplitsTheQualifierFromTheSymbol(string $sql, array $qualifier, string $symbol): void
    {
        $operator = (new DialectParser(Dialect::PostgreSql))->parse($sql)->find('qual_Op')[0];
        $reference = OperatorReferences::read($operator, new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame($qualifier, $reference->qualifier);
        self::assertSame($symbol, $reference->symbol);
    }

    public function testReadRejectsMoreThanADatabaseAndASchema(): void
    {
        $operator = (new DialectParser(Dialect::PostgreSql))->parse('SELECT 1 OPERATOR(a.b.c.+) 2')->find('qual_Op')[0];
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        OperatorReferences::read($operator, new Scope(new Identifiers(Dialect::PostgreSql)));
    }

    /**
     * @param list<string> $qualifier
     */
    #[TestWith([[], '+', false, '+'])]
    #[TestWith([['pg_catalog'], '~~*', false, 'ILIKE'])]
    #[TestWith([['pg_catalog'], '-', true, '-'])]
    #[TestWith([['pg_catalog'], '*', true, null])]
    #[TestWith([['public'], '+', false, null])]
    #[TestWith([['db', 'pg_catalog'], '+', false, null])]
    #[TestWith([[], '<->', false, null])]
    public function testBuiltinNamesOnlyClassifiedOperatorsOfTheDefaultPath(array $qualifier, string $symbol, bool $prefix, ?string $expected): void
    {
        self::assertSame($expected, OperatorReferences::builtin(new QualifiedOperator($qualifier, $symbol), $prefix));
    }
}
