<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Analysis\ExpressionReader::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ExpressionReaderTest extends TestCase
{
    public function testReadDoesNotTurnUnimplementedFunctionsIntoUnknownFacts(): void
    {
        $this->expectException(\SqlSemantics\Core\SemanticException::class);
        SemanticCases::select(Dialect::Sqlite, 'SELECT ABS(foo) FROM bar');
    }

    public function testColumnDistinguishesQualifierAndColumnName(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT b.foo FROM bar b');
        self::assertSame('b.foo', $statement->field('foo')->expression->toString());
    }

    public function testTerminalPreservesNullAndParameterUncertainty(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT NULL AS n, ? AS p');
        self::assertInstanceOf(\SqlSemantics\Semantic\Type\Undetermined::class, $statement->field('n')->type);
        self::assertInstanceOf(\SqlSemantics\Semantic\Type\Undetermined::class, $statement->field('p')->type);
        self::assertSame('null-literal', $statement->field('n')->type->reason->value);
        self::assertSame('parameter-not-supplied', $statement->field('p')->type->reason->value);
    }

    public function testOperationUsesParserGroupingInsteadOfFlatTokenOrder(): void
    {
        $db = new PDO('sqlite::memory:');
        $query = SemanticCases::select(Dialect::Sqlite, 'SELECT (1 + 2) * 3 AS n');
        $result = $db->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(9, $result->fetchColumn());
    }

    public function testCallRetainsOrderedSemanticOperands(): void
    {
        $query = SemanticCases::select(Dialect::Sqlite, 'SELECT COALESCE(NULL, 3, 4) AS n');
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\Coalesce::class, $query->field('n')->expression);
        self::assertCount(3, $query->field('n')->expression->operands);
    }
    public function testTerminalRecognizesUnshadowedSqliteBooleanLiterals(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT TRUE AS truth, FALSE AS falsehood');
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\Literal::class, $statement->field('truth')->expression);
        self::assertTrue($statement->field('truth')->expression->value);
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\Literal::class, $statement->field('falsehood')->expression);
        self::assertFalse($statement->field('falsehood')->expression->value);
    }
}
