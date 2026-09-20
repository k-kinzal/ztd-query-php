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

#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
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
final class FromReaderTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testJoinedBindsOnBeforeIntroducingThisJoinsNulls(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT b.score FROM users a LEFT JOIN users b ON a.id=b.id WHERE b.score > 0');
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertNotNull($query->from->condition);
        self::assertSame(Nullability::NotNull, $query->from->condition->operands[1]->nullability);
        self::assertNotNull($query->where);
        self::assertSame(Nullability::MaybeNull, $query->where->operands[0]->nullability);
        self::assertSame(['j0'], $query->where->operands[0]->nullExtendedBy);
    }
    public function testJoinPropagatesNestedOuterJoinProvenance(): void
    {
        $query = (new AnalysisCase())->query('SELECT a.id, b.id, c.id FROM users a LEFT JOIN users b ON a.id=b.id RIGHT JOIN users c ON b.id=c.id');
        self::assertSame(['j0'], $query->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j1', 'j0'], $query->outputs[1]->expression->nullExtendedBy);
        self::assertSame([], $query->outputs[2]->expression->nullExtendedBy);
    }
    public function testRelationFullJoinExtendsBothSides(): void
    {
        $query = (new AnalysisCase())->query('SELECT a.id, b.id FROM users a FULL JOIN users b ON a.id=b.id');
        self::assertSame(['j0'], $query->outputs[0]->expression->nullExtendedBy);
        self::assertSame(['j0'], $query->outputs[1]->expression->nullExtendedBy);
    }
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testReadCrossJoinHasNoMatchPredicate(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT a.id FROM users a CROSS JOIN users b');
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame(\SqlSemantics\Model\JoinKind::Cross, $query->from->kind);
        self::assertNull($query->from->condition);
    }


    public function testSqliteRespectsExplicitDatabaseNames(): void
    {
        $case = new AnalysisCase(Dialect::Sqlite);
        $catalog = $case->schema('CREATE TABLE main.users (id INTEGER)');
        $query = $case->analyzer->analyze($case->parser->parse('SELECT u.id FROM main.users AS u'), $catalog);
        self::assertSame('main', $query->relations[0]->declaration->schema);
    }

    public function testTableRejectsAliasColumnLists(): void
    {
        $this->expectException(SemanticException::class);
        (new AnalysisCase())->query('SELECT renamed FROM users AS u(renamed)');
    }

    public function testKindRecognizesRightAndRejectsNatural(): void
    {
        $case = new AnalysisCase();
        $tables = new \SqlSemantics\Binding\TableResolver($case->schema(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public');
        $reader = new \SqlSemantics\Analysis\FromReader($tables, new \SqlSemantics\Binding\IdentitySequence());
        $source = $case->parser->parse('SELECT 1');
        self::assertSame(\SqlSemantics\Model\JoinKind::Right, $reader->kind('RIGHT OUTER JOIN', $source));
        $this->expectException(SemanticException::class);
        $reader->kind('NATURAL JOIN', $source);
    }
}
