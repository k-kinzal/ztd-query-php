<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Syntax\Document;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Document::class)]
#[CoversClass(\SqlFormatter\Syntax\Analyzer::class)]
#[CoversClass(\SqlFormatter\Syntax\Brackets::class)]
#[CoversClass(\SqlFormatter\Syntax\Expressions::class)]
#[CoversClass(\SqlFormatter\Syntax\Headers::class)]
#[CoversClass(\SqlFormatter\Syntax\Markers::class)]
#[CoversClass(\SqlFormatter\Syntax\Rules::class)]
#[CoversClass(\SqlFormatter\Syntax\Lists::class)]
final class MarkersTest extends TestCase
{
    public function testApplyPreservesBetweenConjunction(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT a FROM t WHERE a BETWEEN 1 AND 3 AND b=1'));
        self::assertCount(1, $document->logical);
        self::assertCount(3, $document->clauses);
    }
}
