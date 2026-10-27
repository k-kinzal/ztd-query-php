<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\RowShape;

#[CoversClass(ShowFacts::class)]
#[Medium]
final class ShowFactsTest extends TestCase
{
    public function testShapeReadsStrictModeFromTheSessionSnapshot(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'), strict: false);
        $show = $semantics->analyze('SHOW FULL COLUMNS FROM t', $semantics->context([], true, null, $settings));

        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $show->field(0)->nullability);
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $show->field(6)->nullability);
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::Nullable, $show->field(7)->nullability);
    }

    public function testDeriveResolvesTheWhereConditionAgainstTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW VARIABLES WHERE Value = 1 AND nope = 2', []);
        self::assertSame('Column nope does not exist.', $show->diagnostics()[0]->message());
    }

    public function testDeriveKeepsWindowNamesInTheInspectionScope(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW EVENTS FROM d WHERE DENSE_RANK() OVER missing');
        $diagnostic = $show->diagnostics()[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse::class, $diagnostic);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::UnknownWindow, $diagnostic->rule);
        self::assertInstanceOf(\SqlSemantics\Statement\Identifier\Name::class, $diagnostic->name);
        self::assertSame('missing', $diagnostic->name->value);
    }

    public function testRowsRecordsTheLayout(): void
    {
        self::assertSame('Privilege', (new Semantics(Dialect::MySql))->analyze('SHOW PRIVILEGES')->field(0)->name?->value);
    }

    public function testFactReplacesTheFirstName(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze("SHOW DATABASES LIKE 'x'");
        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $show);
        self::assertSame('Database (x)', $show->relationInformation()->shape->slots[0]->name?->value);
    }

    public function testShapeAnswersTheLayoutOfTheRelease(): void
    {
        self::assertCount(9, (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW FULL COLUMNS FROM t')->fields() ?? []);
    }

    public function testOpenNamesTheMissingInput(): void
    {
        self::assertSame(['the session state: x'], array_map(static fn (\SqlSemantics\Statement\Reference\Missing\MissingInput $missing): string => $missing->describe(), (new ShowFacts())->open(new SessionState('x'))->shape->missing));
    }

    public function testQueryAnswersAnOpenProjectionForAnOpenShape(): void
    {
        self::assertInstanceOf(OpenStar::class, (new ShowFacts())->query(new RowShape([], [new SessionState('x')]), Comparison::Sensitive)->projection[0]);
    }

    public function testDatabasePrefersTheWrittenName(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW TABLES FROM db');
        self::assertSame('Tables_in_db', $show->field(0)->name?->value);
    }

    public function testCurrentDatabaseDescribesTheSession(): void
    {
        self::assertSame('the session state: the current database', (new ShowFacts())->currentDatabase()->describe());
    }

    public function testLimitDerivesTheOperands(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW WARNINGS LIMIT ?, ?');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings::class, $show);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit::class, $show->limit);
        self::assertTrue($show->limit->count->hasBinding());
    }
}
