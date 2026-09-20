<?php

declare(strict_types=1);

namespace Tests\Unit\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Binding\Scope::class)]
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
final class ScopeTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testColumnRejectsAmbiguousUnqualifiedNames(Dialect $dialect): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('unambiguously');
        (new AnalysisCase($dialect))->query('SELECT id FROM users a JOIN users b ON a.id=b.id');
    }
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testMatchesAliasHidesOriginalTableName(Dialect $dialect): void
    {
        $this->expectException(SemanticException::class);
        (new AnalysisCase($dialect))->query('SELECT users.id FROM users AS child');
    }
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testCombineRejectsDuplicateAliases(Dialect $dialect): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Duplicate relation');
        (new AnalysisCase($dialect))->query('SELECT a.id FROM users a, users a');
    }
    public function testRejectsReferencesOutsideJoinOperands(): void
    {
        $this->expectException(SemanticException::class);
        (new AnalysisCase())->query('SELECT a.id FROM users a, users b JOIN users c ON a.id = c.id');
    }


    public function testExtendDoesNotMutateTheInputScope(): void
    {
        $query = (new AnalysisCase())->query('SELECT id FROM users');
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), $query->relations);
        $extended = $scope->extend('j0');
        self::assertSame([], $scope->extensions);
        self::assertSame(['r0' => ['j0']], $extended->extensions);
        self::assertSame(['r0' => ['j0', 'j1']], $extended->extend('j1')->extensions);
    }
}
