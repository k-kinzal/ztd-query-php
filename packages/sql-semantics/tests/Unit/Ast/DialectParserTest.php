<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Dialect;

#[CoversClass(DialectParser::class)]
#[Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Analysis::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ExpressionEdit::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
final class DialectParserTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'parse_toplevel', 'pg-17.2'])]
    #[TestWith([Dialect::MySql, 'start_entry', 'mysql-8.4.7'])]
    #[TestWith([Dialect::Sqlite, 'input', 'sqlite-3.47.2'])]
    public function testParseUsesTheRequestedGrammarAndRetainsSql(Dialect $dialect, string $root, string $version): void
    {
        $parser = new DialectParser($dialect, $version);
        $sql = '/* text */ SELECT 1;';
        $tree = $parser->parse($sql);
        self::assertSame($root, $tree->name);
        self::assertSame($sql, $tree->toString());
    }

    #[TestWith([Dialect::PostgreSql, 'pg-17.2'])]
    #[TestWith([Dialect::MySql, 'mysql-8.4.7'])]
    #[TestWith([Dialect::Sqlite, 'sqlite-3.47.2'])]
    public function testVersionReturnsTheResolvedRelease(Dialect $dialect, string $version): void
    {
        self::assertSame($version, (new DialectParser($dialect))->version());
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testRejectsAnUnavailableGrammarRelease(Dialect $dialect): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported');
        new DialectParser($dialect, 'unavailable-release');
    }
}
