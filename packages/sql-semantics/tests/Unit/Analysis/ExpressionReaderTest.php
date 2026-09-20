<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
#[CoversClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SelectReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Analysis\TailReader::class)]
#[CoversClass(\SqlSemantics\Analysis\TypeResolution::class)]
#[CoversClass(\SqlSemantics\Analyzer::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\SelectQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema\Catalog::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class ExpressionReaderTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testOperationPreservesOperatorPrecedence(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT 1+2*3 AS result');
        self::assertSame('+', $query->outputs[0]->expression->symbol);
        self::assertSame('*', $query->outputs[0]->expression->operands[1]->symbol);
    }
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testReadsParenthesesAndUnaryOperators(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT -(score + 1) AS negative FROM users WHERE NOT (score > 0)');
        self::assertSame('-', $query->outputs[0]->expression->symbol);
        self::assertSame('+', $query->outputs[0]->expression->operands[0]->symbol);
        self::assertSame('NOT', $query->where?->symbol);
    }


    public function testTokenRejectsUnmodeledTerminals(): void
    {
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql));
        $this->expectException(SemanticException::class);
        (new \SqlSemantics\Analysis\ExpressionReader())->token(new \SqlParser\Lexer\Token(0, 'BCONST', "B'101'", 0), $scope);
    }

    public function testQualifiedRejectsNonNameOperands(): void
    {
        $tree = (new \SqlParser\Sqlite\SqliteParser())->parse('SELECT a.id');
        $reader = new \SqlSemantics\Analysis\ExpressionReader();
        self::assertTrue($reader->qualified(\SqlSemantics\Ast\Tree::significant($tree->find('expr')[0])));
        self::assertFalse($reader->qualified([]));
        self::assertFalse($reader->qualified([new \SqlParser\Lexer\Token(1, 'ID', 'a', 0)]));
    }

    public function testCallRetainsNestedCoalesceInputs(): void
    {
        $query = (new AnalysisCase())->query('SELECT COALESCE(parent_id, COALESCE(NULL, score)) FROM users');
        self::assertSame(Nullability::NotNull, $query->outputs[0]->expression->nullability);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Coalesce, $query->outputs[0]->expression->operands[1]->kind);
    }
}
