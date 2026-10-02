<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\Input as I;
use SqlSemantics\Statement\Construction as C;

#[CoversClass(I\CaseInputReader::class)]
#[Small]
final class CaseInputReaderTest extends TestCase
{
    public function testReadPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT CASE 1 WHEN 1 THEN 7 ELSE 9 END')->find('expr')[0];
        $input = (new I\CaseInputReader())->read($source, new I\ExpressionInputReader());
        self::assertNotNull($input);
        self::assertSame('CASE 1 WHEN 1 THEN 7 ELSE 9 END', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testArmsPreservesWhenThenPairingAndOrder(): void
    {
        $source = (new SqliteParser())->parse('SELECT CASE WHEN 1 THEN 7 WHEN 2 THEN 8 END')->find('case_exprlist')[0];
        $arms = (new I\CaseInputReader())->arms($source, new I\ExpressionInputReader());
        self::assertCount(2, $arms);
        $branches = new C\Conditional\CaseBranchesInput(null, ...$arms);
        self::assertSame('WHEN 1 THEN 7 WHEN 2 THEN 8', (new C\Rendering\ExpressionSql())->branches($branches));
    }
}
