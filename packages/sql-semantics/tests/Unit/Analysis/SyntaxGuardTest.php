<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\SemanticException;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
#[CoversClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SelectReader::class)]
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
final class SyntaxGuardTest extends TestCase
{
    #[DataProvider('providerUnsupported')]
    public function testSelectRejectsUnmodeledConstructs(string $sql): void
    {
        $this->expectException(SemanticException::class);
        (new AnalysisCase())->query($sql);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnsupported(): iterable
    {
        yield 'aggregate' => ['SELECT COUNT(*) FROM users'];
        yield 'grouping' => ['SELECT score FROM users GROUP BY score'];
        yield 'union' => ['SELECT id FROM users UNION SELECT id FROM users'];
        yield 'cte' => ['WITH u AS (SELECT id FROM users) SELECT id FROM u'];
        yield 'scalar subquery' => ['SELECT (SELECT id FROM users)'];
        yield 'using' => ['SELECT * FROM users a JOIN users b USING (id)'];
        yield 'natural' => ['SELECT * FROM users a NATURAL JOIN users b'];
        yield 'unknown function' => ['SELECT mystery(id) FROM users'];
        yield 'distinct on' => ['SELECT DISTINCT ON (id) id FROM users'];
        yield 'locking' => ['SELECT id FROM users FOR UPDATE'];
        yield 'insert select' => ['INSERT INTO users (id) SELECT id FROM users'];
    }

}
