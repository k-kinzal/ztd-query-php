<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\Operators;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operators::class)]
#[Medium]
final class OperatorsTest extends TestCase
{
    public function testCreateKeepsTheLastArgumentAndIgnoresUnknownAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (function = f, rightarg = integer, "Hashes", function = g, gtcmp = >)');
        self::assertInstanceOf(Statement\CreateOperatorStatement::class, $statement);
        self::assertSame([OperatorAttribute::RightArg, OperatorAttribute::Function, OperatorAttribute::Merges], array_map(static fn ($option) => $option->attribute, $statement->options));
        self::assertSame(['g'], $statement->options[1]->value instanceof \SqlSemantics\Model\Relation\QualifiedName ? $statement->options[1]->value->parts : null);
    }

    #[TestWith(['CREATE OPERATOR === (function = f, leftarg = integer)', 'definition-requirement'])]
    #[TestWith(['CREATE OPERATOR === (function = f, rightarg = integer, commutator)', 'definition-argument'])]
    #[TestWith(['CREATE OPERATOR === (function = f, rightarg = SETOF integer)', 'set-of-declaration'])]
    #[TestWith(['ALTER OPERATOR = (integer, integer) SET (function = f)', 'definition-attribute'])]
    #[TestWith(['ALTER OPERATOR = (integer, integer) SET (sort1 = <)', 'definition-attribute'])]
    #[TestWith(['ALTER OPERATOR = (integer, integer) SET (hashes = 2)', 'definition-argument'])]
    public function testCreateAndAlterDiagnoseImpossibleDefinitions(string $sql, string $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::from($violation)->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testAlterKeepsTheLastArgument(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR = (integer, integer) SET (restrict = a, join = b, restrict = NONE)');
        self::assertInstanceOf(Statement\AlterOperatorStatement::class, $statement);
        self::assertSame(OperatorAttribute::Restrict, $statement->options[1]->attribute);
        self::assertNull($statement->options[1]->value);
    }

    public function testAttributeResolvesAliasesAndIgnoresOtherCases(): void
    {
        self::assertSame(OperatorAttribute::Function, Operators::attribute('procedure'));
        self::assertSame(OperatorAttribute::Merges, Operators::attribute('sort2'));
        self::assertNull(Operators::attribute('LEFTARG'));
        self::assertNull(Operators::attribute('unknown'));
    }

    public function testOptionRejectsASetOfOperand(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SetOfDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (function = f, leftarg = SETOF text, rightarg = integer)');
    }

    public function testDropReadsEveryOperator(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR IF EXISTS + (integer, integer), s.- (NONE, integer) CASCADE');
        self::assertInstanceOf(Statement\DropOperatorsStatement::class, $statement);
        self::assertCount(2, $statement->operators);
        self::assertSame(['s', '-'], $statement->operators[1]->name->parts);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
    }

    public function testDropRejectsAnIncompleteSignature(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::OperatorSignature->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR + (integer)');
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsEveryOperatorDefinition')]
    public function testBindReadsEveryOperatorDefinition(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsEveryOperatorDefinition(): iterable
    {
        return [
            'CREATE OPERATOR s.=== (FUNCTION = f, LEFTARG = int, RIGHTARG = int) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OPERATOR s.=== (FUNCTION = f, LEFTARG = int, RIGHTARG = int)', 'CREATE OPERATOR "s".=== (FUNCTION = "f", LEFTARG = integer, RIGHTARG = integer)'],
            'ALTER OPERATOR === (int, int) SET (RESTRICT = r, JOIN = j) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'ALTER OPERATOR === (int, int) SET (RESTRICT = r, JOIN = j)', 'ALTER OPERATOR === (integer, integer) SET (RESTRICT = "r", JOIN = "j")'],
            'ALTER OPERATOR === (int, int) SET (RESTRICT = r, RESTRICT = s) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'ALTER OPERATOR === (int, int) SET (RESTRICT = r, RESTRICT = s)', 'ALTER OPERATOR === (integer, integer) SET (RESTRICT = "s")'],
            'DROP OPERATOR IF EXISTS === (int, int), !== (int, NONE) CASCADE (PostgreSql)' => [Dialect::PostgreSql, null, [], 'DROP OPERATOR IF EXISTS === (int, int), !== (int, NONE) CASCADE', 'DROP OPERATOR IF EXISTS === (integer, integer), !== (integer, NONE) CASCADE'],
            'CREATE OPERATOR === (PROCEDURE = f, LEFTARG = int, RIGHTARG = int, sort1 = <) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OPERATOR === (PROCEDURE = f, LEFTARG = int, RIGHTARG = int, sort1 = <)', 'CREATE OPERATOR === (FUNCTION = "f", LEFTARG = integer, RIGHTARG = integer, MERGES = TRUE)'],
            'CREATE OPERATOR === (FUNCTION = f, LEFTARG = int, RIGHTARG = int, merges, hashes) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OPERATOR === (FUNCTION = f, LEFTARG = int, RIGHTARG = int, merges, hashes)', 'CREATE OPERATOR === (FUNCTION = "f", LEFTARG = integer, RIGHTARG = integer, MERGES = TRUE, HASHES = TRUE)'],
            'CREATE OPERATOR === (FUNCTION = f, LEFTARG = int, RIGHTARG = int, merges = false) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OPERATOR === (FUNCTION = f, LEFTARG = int, RIGHTARG = int, merges = false)', 'CREATE OPERATOR === (FUNCTION = "f", LEFTARG = integer, RIGHTARG = integer, MERGES = FALSE)'],
            'CREATE OPERATOR === (FUNCTION = f, LEFTARG = int, RIGHTARG = int, unknownattr = 1) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OPERATOR === (FUNCTION = f, LEFTARG = int, RIGHTARG = int, unknownattr = 1)', 'CREATE OPERATOR === (FUNCTION = "f", LEFTARG = integer, RIGHTARG = integer)'],
            'CREATE OPERATOR === (FUNCTION = f, LEFTARG = int, RIGHTARG = int, FUNCTION = g) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OPERATOR === (FUNCTION = f, LEFTARG = int, RIGHTARG = int, FUNCTION = g)', 'CREATE OPERATOR === (LEFTARG = integer, RIGHTARG = integer, FUNCTION = "g")'],
            'CREATE OPERATOR === (FUNCTION = f, RIGHTARG = int, "Function" = g) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OPERATOR === (FUNCTION = f, RIGHTARG = int, "Function" = g)', 'CREATE OPERATOR === (FUNCTION = "f", RIGHTARG = integer)'],
            'CREATE OPERATOR === (FUNCTION = f, RIGHTARG = int, ltcmp = <, gtcmp = >) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OPERATOR === (FUNCTION = f, RIGHTARG = int, ltcmp = <, gtcmp = >)', 'CREATE OPERATOR === (FUNCTION = "f", RIGHTARG = integer, MERGES = TRUE)'],
        ];
    }

    #[TestWith(['CREATE OPERATOR === (FUNCTION = f, LEFTARG = setof int)'])]
    #[TestWith(['CREATE OPERATOR === (FUNCTION = f, RIGHTARG = setof int)'])]
    #[TestWith(['CREATE OPERATOR === (FUNCTION = f, LEFTARG)'])]
    #[TestWith(['CREATE OPERATOR a.b.=== (FUNCTION = f, LEFTARG = int)'])]
    #[TestWith(['CREATE OPERATOR === (LEFTARG = int)'])]
    #[TestWith(['ALTER OPERATOR === (int, int) SET (sort1 = <)'])]
    #[TestWith(['ALTER OPERATOR === (int, int) SET (LEFTARG = int)'])]
    #[TestWith(['ALTER OPERATOR === (int, int) SET (bogus = int)'])]
    public function testBindRejectsUnsupportedOperatorAttributes(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
