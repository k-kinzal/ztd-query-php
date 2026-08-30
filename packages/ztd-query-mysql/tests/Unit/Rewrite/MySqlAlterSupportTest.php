<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\MySqlAlterStatements;
use ZtdQuery\Platform\MySql\Dialect\MySqlStatementOptions;
use ZtdQuery\Platform\MySql\Parse\MySqlParser;
use ZtdQuery\Platform\MySql\Rewrite\MySqlAlterSupport;

#[CoversClass(MySqlAlterSupport::class)]
#[UsesClass(MySqlStatementOptions::class)]
#[UsesClass(MySqlParser::class)]
final class MySqlAlterSupportTest extends TestCase
{
    public function testRefusesStatementWhereTheStatementAsWrittenNamesAColumnDefault(): void
    {
        $support = new MySqlAlterSupport();

        self::assertSame(
            [true, true, true, false],
            [
                $support->refusesStatement('ALTER TABLE t ALTER COLUMN a SET DEFAULT 1'),
                $support->refusesStatement('ALTER TABLE t ALTER COLUMN a DROP DEFAULT'),
                $support->refusesStatement('ALTER TABLE t ORDER BY a'),
                $support->refusesStatement('ALTER TABLE t ADD COLUMN a INT'),
            ],
        );
    }

    public function testRefusesOperationWhereAKeywordPairNamesWhatTheShadowCannotHold(): void
    {
        $support = new MySqlAlterSupport();

        self::assertSame(
            [true, true, true],
            [
                $support->refusesOperation(MySqlAlterStatements::operation('ALTER TABLE t ADD INDEX i (a)')),
                $support->refusesOperation(MySqlAlterStatements::operation('ALTER TABLE t DROP INDEX i')),
                $support->refusesOperation(MySqlAlterStatements::operation('ALTER TABLE t RENAME INDEX i TO j')),
            ],
        );
    }

    public function testRefusesOperationWhereAKeywordAloneNamesWhatTheShadowCannotHold(): void
    {
        $support = new MySqlAlterSupport();

        self::assertSame(
            [true, true],
            [
                $support->refusesOperation(MySqlAlterStatements::operation('ALTER TABLE t ENGINE = InnoDB')),
                $support->refusesOperation(MySqlAlterStatements::operation('ALTER TABLE t DROP PARTITION p1')),
            ],
        );
    }

    public function testRefusesNothingOfAnOperationTheShadowCanCarry(): void
    {
        $support = new MySqlAlterSupport();

        self::assertFalse($support->refusesOperation(MySqlAlterStatements::operation('ALTER TABLE t ADD COLUMN a INT')));
    }

    public function testAnySetReportsWhetherAnyOfTheKeywordsWasWritten(): void
    {
        $support = new MySqlAlterSupport();
        $operation = MySqlAlterStatements::operation('ALTER TABLE t ADD COLUMN a INT');

        self::assertSame(
            [true, false],
            [
                $support->anySet($operation->options, ['DROP', 'ADD']),
                $support->anySet($operation->options, ['RENAME']),
            ],
        );
    }

    public function testSaysAnyOfReportsWhetherTheOperationCarriesOneOfThoseWords(): void
    {
        $support = new MySqlAlterSupport();
        $operation = MySqlAlterStatements::operation('ALTER TABLE t ADD COLUMN a INT');

        self::assertSame(
            [true, false],
            [
                $support->saysAnyOf($operation, ['INT']),
                $support->saysAnyOf($operation, ['ZZZ']),
            ],
        );
    }

    public function testSaysAnythingWithinReportsWhetherAWordIsWrittenAroundOneOfThose(): void
    {
        $support = new MySqlAlterSupport();
        $operation = MySqlAlterStatements::operation('ALTER TABLE t ADD COLUMN a INT');

        self::assertSame(
            [true, false],
            [
                $support->saysAnythingWithin($operation, ['IN']),
                $support->saysAnythingWithin($operation, ['PARTITION']),
            ],
        );
    }

    public function testUnknownWordsAnswersWhatTheParserDidNotTake(): void
    {
        $support = new MySqlAlterSupport();

        self::assertContains('INT', $support->unknownWords(MySqlAlterStatements::operation('ALTER TABLE t ADD COLUMN a INT')));
    }

}
