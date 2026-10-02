<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\Input as I;
use SqlSemantics\Statement\Construction as C;

#[CoversClass(I\SubqueryInputReader::class)]
#[Small]
final class SubqueryInputReaderTest extends TestCase
{
    public function testReadPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT EXISTS (SELECT 1 WHERE 0)')->find('expr')[0];
        $input = (new I\SubqueryInputReader())->read($source, new I\ExpressionInputReader());
        self::assertNotNull($input);
        self::assertSame('EXISTS (SELECT 1 WHERE 0)', (new C\Rendering\ExpressionSql())->write($input));
    }
}
