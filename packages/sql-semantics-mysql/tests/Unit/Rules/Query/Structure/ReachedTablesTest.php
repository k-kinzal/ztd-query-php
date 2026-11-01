<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReachedTables;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ReachedTables::class)]
#[Small]
final class ReachedTablesTest extends TestCase
{
    public function testProblemFindsTheModifiersOfADefinitionTheStatementReadsByItsExactNameOnly(): void
    {
        $table = new CommonTableExpression(new Name('c'), [], new Select([SelectOption::HighPriority], [new SelectExpression(new NumberLiteral('1'))]));
        $with = new With(false, [$table]);
        $reads = new Select([], [new Star()], new TableReference(new QualifiedName(new Name('c'))));
        $other = new Select([], [new Star()], new TableReference(new QualifiedName(new Name('C'))));

        self::assertSame("Incorrect usage/placement of 'HIGH_PRIORITY'", (new ReachedTables())->problem($with, [$reads])?->message());
        self::assertNull((new ReachedTables())->problem($with, [$other]));
        self::assertNull((new ReachedTables())->problem($with, [new Select([], [new SelectExpression(new NumberLiteral('2'))])]));
        self::assertNull((new ReachedTables())->problem(null, [$reads]));
    }

    public function testProblemFollowsADefinitionThroughAnotherOneAndReportsAllWithDistinct(): void
    {
        $first = new CommonTableExpression(new Name('c'), [], new Select([SelectOption::All, SelectOption::Distinct], [new SelectExpression(new NumberLiteral('1'))]));
        $second = new CommonTableExpression(new Name('d'), [], new Select([], [new Star()], new TableReference(new QualifiedName(new Name('c')))));
        $with = new With(false, [$first, $second]);
        $reads = new Select([], [new Star()], new TableReference(new QualifiedName(new Name('d'))));

        self::assertSame('Incorrect usage of ALL and DISTINCT', (new ReachedTables())->problem($with, [[$reads]])?->message());
    }

    public function testNodesCollectsNodesAndListsOfNodes(): void
    {
        $one = new NumberLiteral('1');
        $two = new NumberLiteral('2');

        self::assertSame([$one, $two], (new ReachedTables())->nodes([null, $one, [$two]]));
    }
}
