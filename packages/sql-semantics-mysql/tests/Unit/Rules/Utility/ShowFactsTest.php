<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\OpenStar;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowFacts::class)]
#[Medium]
final class ShowFactsTest extends TestCase
{
    public function testDeriveResolvesTheWhereConditionAgainstTheRows(): void
    {
        $show = (new Inspection())->diagnose('SHOW VARIABLES WHERE Value = 1 AND nope = 2', []);
        self::assertSame('Column nope does not exist.', $show->facts->diagnostics[0]->message());
    }

    public function testRowsRecordsTheLayout(): void
    {
        self::assertSame('Privilege', (new Inspection())->diagnose('SHOW PRIVILEGES')->field(0)->name?->value);
    }

    public function testFactReplacesTheFirstName(): void
    {
        $show = (new Inspection())->diagnose("SHOW DATABASES LIKE 'x'");
        self::assertSame('Database (x)', $show->facts->relation($show->statement)->shape->slots[0]->name?->value);
    }

    public function testShapeAnswersTheLayoutOfTheRelease(): void
    {
        self::assertCount(9, (new Inspection('mysql-5.6.51'))->diagnose('SHOW FULL COLUMNS FROM t')->fields() ?? []);
    }

    public function testOpenNamesTheMissingInput(): void
    {
        self::assertSame(['the session state: x'], array_map(static fn (\SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\MissingInput $missing): string => $missing->describe(), (new ShowFacts())->open(new SessionState('x'))->shape->missing));
    }

    public function testQueryAnswersAnOpenProjectionForAnOpenShape(): void
    {
        self::assertInstanceOf(OpenStar::class, (new ShowFacts())->query(new RowShape([], [new SessionState('x')]), Comparison::Sensitive)->projection[0]);
    }

    public function testDatabasePrefersTheWrittenName(): void
    {
        $show = (new Inspection())->diagnose('SHOW TABLES FROM db');
        self::assertSame('Tables_in_db', $show->field(0)->name?->value);
    }

    public function testCurrentDatabaseDescribesTheSession(): void
    {
        self::assertSame('the session state: the current database', (new ShowFacts())->currentDatabase()->describe());
    }

    public function testLimitDerivesTheOperands(): void
    {
        $show = (new Inspection())->diagnose('SHOW WARNINGS LIMIT ?, ?');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings::class, $show->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit::class, $show->statement->limit);
        self::assertTrue($show->facts->covers($show->statement->limit->count));
    }
}
