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

#[CoversClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
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
final class ProjectionReaderTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testItemExpandsStarsAndPreservesDuplicateNames(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT a.*, b.score AS id FROM users a LEFT JOIN users b ON a.id=b.id');
        self::assertSame(['id', 'parent_id', 'score', 'id'], array_column($query->outputs, 'name'));
        self::assertSame([0, 1, 2, 3], array_column($query->outputs, 'ordinal'));
    }
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testReadExpandsUnqualifiedStarInFromOrder(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT * FROM users a, users b');
        self::assertSame(['id', 'parent_id', 'score', 'id', 'parent_id', 'score'], array_column($query->outputs, 'name'));
        self::assertSame('r0', $query->outputs[0]->expression->binding?->relationId);
        self::assertSame('r1', $query->outputs[3]->expression->binding?->relationId);
    }


    public function testStarRejectsAnUnknownQualifier(): void
    {
        $this->expectException(SemanticException::class);
        (new AnalysisCase())->query('SELECT absent.* FROM users');
    }

    public function testItemResolvesBarePostgresUnknownLiteralsAsText(): void
    {
        $query = (new AnalysisCase())->query("SELECT NULL, 'hello'");
        self::assertSame('text', $query->outputs[0]->expression->type->name);
        self::assertSame('text', $query->outputs[1]->expression->type->name);
        self::assertSame(\SqlSemantics\Type\Nullability::AlwaysNull, $query->outputs[0]->expression->nullability);
    }

    public function testItemDecodesMysqlStringAliases(): void
    {
        $query = (new AnalysisCase(Dialect::MySql))->query("SELECT score AS 'points' FROM users ORDER BY points");
        self::assertSame('points', $query->outputs[0]->name);
        self::assertSame($query->outputs[0]->expression, $query->orderBy[0]->expression);
    }
}
