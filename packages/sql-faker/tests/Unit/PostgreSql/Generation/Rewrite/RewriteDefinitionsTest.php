<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\RewriteDefinitions;

#[CoversClass(RewriteDefinitions::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenRewriter::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\UniqueOptionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\CopySourceRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\FetchWithTiesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\FunctionNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\HashPartitionBoundRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\LimitOffsetRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\LookaheadRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\OperatorArgumentsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\OverlapsArgumentsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\PublicationObjectRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\RelationNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\TimeZoneIntervalRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\ConstraintAttributesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\AnyRelationNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\IndirectionStarRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\SelectOptionsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\TableFunctionRule::class)]
final class RewriteDefinitionsTest extends TestCase
{
    public function testCreateComposesTheDeclaredSourceRules(): void
    {
        $input = TerminalSequence::fromNames(['NOT', 'LIKE']);
        $result = (new RewriteDefinitions())->create()->rewrite($input);
        self::assertSame(['NOT_LA', 'LIKE'], $result->names());
        self::assertSame($input->original, $result->original);
    }

    #[DataProvider('providerColumnScopes')]
    public function testCreateKeepsOneCollationPerSourceColumnQualifierList(string $scope): void
    {
        $first = new TerminalOccurrence('COLLATE', 10, [0, 1], [$scope, 'ColConstraint']);
        $firstName = new TerminalOccurrence('IDENT', 11, [0, 1], [$scope, 'ColConstraint']);
        $duplicate = new TerminalOccurrence('COLLATE', 12, [0, 2], [$scope, 'ColConstraint']);
        $duplicateName = new TerminalOccurrence('IDENT', 13, [0, 2], [$scope, 'ColConstraint']);
        $other = new TerminalOccurrence('COLLATE', 14, [3, 4], [$scope, 'ColConstraint']);
        $expression = new TerminalOccurrence('COLLATE', 15, [0, 5], [$scope, 'a_expr']);
        $terminals = [$first, $firstName, $duplicate, $duplicateName, $other, $expression];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'ColConstraint', 0),
            new ProductionOccurrence(2, 0, 'ColConstraint', 0), new ProductionOccurrence(3, null, $scope, 0),
            new ProductionOccurrence(4, 3, 'ColConstraint', 0), new ProductionOccurrence(5, 0, 'a_expr', 0),
        ]);
        $rule = (new RewriteDefinitions())->create();
        $result = $rule->rewrite($input);
        self::assertSame([$first, $firstName, $other, $expression], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerColumnScopes(): iterable
    {
        yield 'column definition' => ['columnDef'];
        yield 'partition column options' => ['columnOptions'];
        yield 'domain' => ['CreateDomainStmt'];
    }
}
