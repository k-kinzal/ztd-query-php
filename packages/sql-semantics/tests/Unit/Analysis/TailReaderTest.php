<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Analysis\TailReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
#[CoversClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SelectReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
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
final class TailReaderTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testOrderingResolvesAliasesAndDirection(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT score AS points FROM users ORDER BY points DESC LIMIT 5 OFFSET 2');
        self::assertSame($query->outputs[0]->expression, $query->orderBy[0]->expression);
        self::assertTrue($query->orderBy[0]->descending);
        self::assertSame('5', $query->limit?->symbol);
        self::assertSame('2', $query->offset?->symbol);
    }
    public function testSortExpressionResolvesPositionsAndNullPlacement(): void
    {
        $query = (new AnalysisCase())->query('SELECT parent_id FROM users ORDER BY 1 NULLS LAST');
        self::assertSame($query->outputs[0]->expression, $query->orderBy[0]->expression);
        self::assertFalse($query->orderBy[0]->nullsFirst);
    }
    public function testRejectsOutOfRangeOrderPosition(): void
    {
        $this->expectException(SemanticException::class);
        (new AnalysisCase())->query('SELECT id FROM users ORDER BY 2');
    }


    public function testPaginationReadsMysqlCommaOrder(): void
    {
        $query = (new AnalysisCase(Dialect::MySql))->query('SELECT id FROM users LIMIT 2, 5');
        self::assertSame('5', $query->limit?->symbol);
        self::assertSame('2', $query->offset?->symbol);
    }

    public function testPaginationRejectsFetchWithTiesRatherThanDroppingItsMeaning(): void
    {
        $this->expectException(SemanticException::class);
        (new AnalysisCase())->query('SELECT id FROM users ORDER BY id FETCH FIRST (1+1) ROWS WITH TIES');
    }

    public function testSortExpressionDoesNotResolveAStringLiteralAsAnAlias(): void
    {
        $query = (new AnalysisCase(Dialect::MySql))->query("SELECT score AS points FROM users ORDER BY 'points'");
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Literal, $query->orderBy[0]->expression->kind);
        self::assertSame("'points'", $query->orderBy[0]->expression->symbol);
    }
}
