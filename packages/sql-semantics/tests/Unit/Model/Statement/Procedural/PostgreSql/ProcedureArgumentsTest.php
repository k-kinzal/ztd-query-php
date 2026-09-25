<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Procedural\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\CallProcedureStatement;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\ProcedureArgument;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\ProcedureArguments;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProcedureArguments::class)]
#[Medium]
final class ProcedureArgumentsTest extends TestCase
{
    public function testValidateAcceptsPositionalThenNamedArguments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1, a => 2, b => 3)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        ProcedureArguments::validate($statement->arguments);
        self::assertCount(3, $statement->arguments);
    }

    public function testValidateRejectsAVariadicArgumentBeforeTheLast(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1, 2)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        ProcedureArguments::validate([new ProcedureArgument($statement->arguments[0]->value, null, true), $statement->arguments[1]]);
    }

    public function testValidateRejectsARepeatedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(a => 1, b => 2)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        ProcedureArguments::validate([$statement->arguments[0], new ProcedureArgument($statement->arguments[1]->value, 'a')]);
    }

    public function testValidateRejectsAPositionalArgumentAfterANamedOne(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1, a => 2)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        ProcedureArguments::validate([$statement->arguments[1], $statement->arguments[0]]);
    }

    public function testValidateRejectsAnotherDialect(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $select);
        $value = $select->resultColumns()[0]->expression;
        $this->expectException(InvalidStructure::class);
        ProcedureArguments::validate([new ProcedureArgument($value)]);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerValidateAcceptsOrderedArguments')]
    public function testValidateAcceptsOrderedArguments(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerValidateAcceptsOrderedArguments(): iterable
    {
        return [
            'CALL p(1, 2) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CALL p(1, 2)', 'CALL "p"(1, 2)'],
            'CALL p(1, b => 2) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CALL p(1, b => 2)', 'CALL "p"(1, "b" => 2)'],
            'CALL p(a => 1, b => 2) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CALL p(a => 1, b => 2)', 'CALL "p"("a" => 1, "b" => 2)'],
            'CALL p(VARIADIC ARRAY[1]) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CALL p(VARIADIC ARRAY[1])', 'CALL "p"(VARIADIC ARRAY[1])'],
            'CALL p(1, VARIADIC ARRAY[1]) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CALL p(1, VARIADIC ARRAY[1])', 'CALL "p"(1, VARIADIC ARRAY[1])'],
            'CALL p() (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CALL p()', 'CALL "p"()'],
        ];
    }

    #[TestWith(['CALL p(a => 1, 2)'])]
    #[TestWith(['CALL p(a => 1, a => 2)'])]
    public function testValidateRejectsMisplacedOrRepeatedNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
