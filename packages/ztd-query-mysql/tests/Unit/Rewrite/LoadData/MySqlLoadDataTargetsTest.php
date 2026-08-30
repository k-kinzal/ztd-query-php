<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\LoadData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\MySqlLoadStatements;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Dialect\MySqlValueRenderer;
use ZtdQuery\Platform\MySql\Rewrite\LoadData\MySqlLoadDataTargets;

#[CoversClass(MySqlLoadDataTargets::class)]
#[UsesClass(MySqlValueRenderer::class)]
#[UsesClass(MySqlLexerProfile::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Dialect\MySqlCastRenderer::class)]
final class MySqlLoadDataTargetsTest extends TestCase
{
    public function testOfLoadsIntoEveryColumnTheTableDoesNotWriteItself(): void
    {
        $statement = MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t");

        self::assertSame(
            ['id', 'name'],
            (new MySqlLoadDataTargets())->of($statement, MySqlLoadStatements::definition(), 'sql'),
        );
    }

    public function testOfAnswersTheTargetsTheStatementNamed(): void
    {
        $statement = MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t (name, @v)");

        self::assertSame(
            ['name', '@v'],
            (new MySqlLoadDataTargets())->of($statement, MySqlLoadStatements::definition(), 'sql'),
        );
    }

    public function testOfRefusesToLoadIntoAColumnTheTableWritesItself(): void
    {
        $statement = MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t (total)");

        $this->expectException(UnsupportedSqlException::class);

        (new MySqlLoadDataTargets())->of($statement, MySqlLoadStatements::definition(), 'sql');
    }

    public function testOfRefusesAColumnTheTableDoesNotHave(): void
    {
        $statement = MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t (missing)");

        $this->expectException(UnsupportedSqlException::class);

        (new MySqlLoadDataTargets())->of($statement, MySqlLoadStatements::definition(), 'sql');
    }

    public function testOfRefusesTheSameTargetNamedTwice(): void
    {
        $statement = MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t (name, name)");

        $this->expectException(UnsupportedSqlException::class);

        (new MySqlLoadDataTargets())->of($statement, MySqlLoadStatements::definition(), 'sql');
    }

    public function testTargetInReadsAColumnName(): void
    {
        self::assertSame('name', (new MySqlLoadDataTargets())->targetIn('name', 'sql'));
    }

    public function testTargetInReadsAUserVariableWithItsAtSign(): void
    {
        self::assertSame('@v', (new MySqlLoadDataTargets())->targetIn('@v', 'sql'));
    }

    public function testTargetInRefusesAnEntryThatNamesNothing(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new MySqlLoadDataTargets())->targetIn('', 'sql');
    }

    public function testSetOperationsAnswersWhatEachClauseAssigns(): void
    {
        $statement = MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t (@v) SET name = @v");

        self::assertSame(
            ['name' => '@v'],
            (new MySqlLoadDataTargets())->setOperations($statement, MySqlLoadStatements::definition(), 'sql'),
        );
    }

    public function testSetOperationsRefusesAssigningAColumnTheTableWritesItself(): void
    {
        $statement = MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t (@v) SET total = @v");

        $this->expectException(UnsupportedSqlException::class);

        (new MySqlLoadDataTargets())->setOperations($statement, MySqlLoadStatements::definition(), 'sql');
    }

    public function testIgnoreRowsAnswersHowManyRecordsAreNotData(): void
    {
        $statement = MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t IGNORE 2 LINES");

        self::assertSame(2, (new MySqlLoadDataTargets())->ignoreRows($statement, 'sql'));
    }

    public function testIgnoreRowsIsNoneWhereTheStatementSaidNothing(): void
    {
        $statement = MySqlLoadStatements::statement("LOAD DATA INFILE 'f' INTO TABLE t");

        self::assertSame(0, (new MySqlLoadDataTargets())->ignoreRows($statement, 'sql'));
    }

    public function testRowOfWritesEachValueAsTheSqlThatWouldWriteIt(): void
    {
        self::assertSame(
            ['id' => "CAST('1' AS CHAR)", 'name' => "CAST('a' AS CHAR)"],
            (new MySqlLoadDataTargets())->rowOf(['id', 'name'], [], ['1', 'a']),
        );
    }

    public function testRowOfLeavesAColumnTheRecordNeverHadToTheTablesOwnDefault(): void
    {
        self::assertSame(
            ['id' => "CAST('1' AS CHAR)", 'name' => 'DEFAULT'],
            (new MySqlLoadDataTargets())->rowOf(['id', 'name'], [], ['1']),
        );
    }

    public function testRowOfWritesASetClauseFromWhatWasLoadedIntoTheVariable(): void
    {
        self::assertSame(
            ['name' => "CAST('a' AS CHAR)"],
            (new MySqlLoadDataTargets())->rowOf(['@v'], ['name' => '@v'], ['a']),
        );
    }

    public function testRenderFieldWritesAMissingValueAsNull(): void
    {
        self::assertSame('NULL', (new MySqlLoadDataTargets())->renderField(null));
    }

    public function testRenderFieldWritesAValueAsMySqlWouldWriteIt(): void
    {
        self::assertSame("CAST('a' AS CHAR)", (new MySqlLoadDataTargets())->renderField('a'));
    }

    public function testWithVariablesWritesTheLoadedValueIntoTheExpression(): void
    {
        self::assertSame(
            "UPPER(CAST('a' AS CHAR))",
            (new MySqlLoadDataTargets())->withVariables('UPPER(@v)', ['v' => 'a']),
        );
    }

    public function testWithVariablesLeavesAVariableNothingWasLoadedIntoAlone(): void
    {
        self::assertSame('UPPER(@other)', (new MySqlLoadDataTargets())->withVariables('UPPER(@other)', ['v' => 'a']));
    }
}
