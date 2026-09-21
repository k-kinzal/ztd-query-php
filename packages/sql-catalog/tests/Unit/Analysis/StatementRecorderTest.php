<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\QueryRecord;
use SqlCatalog\Analysis\StatementRecorder;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(StatementRecorder::class)]
#[UsesClass(QueryRecord::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class StatementRecorderTest extends TestCase
{
    public function testRecordKeepsTheStatement(): void
    {
        $recorder = new StatementRecorder();
        $record = $recorder->record(
            new CallSite('a.php', 1, 'f', 'pdo.query'),
            'a.php:0:pdo.query',
            TextPattern::fromText('SELECT 1'),
            StatementKind::Select,
        );
        self::assertSame([$record], $recorder->records());
        self::assertSame(StatementKind::Select, $record->kind);
    }

    public function testFilePreparedFilesAStatementUnderItsHandle(): void
    {
        $recorder = new StatementRecorder();
        $record = $recorder->record(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'h', TextPattern::fromText('SELECT ?'));
        $recorder->filePrepared('h', [$record]);
        self::assertSame([$record], $recorder->prepared('h'));
    }

    public function testPreparedIsEmptyForAHandleNothingWasFiledUnder(): void
    {
        self::assertSame([], (new StatementRecorder())->prepared('missing'));
    }

    public function testRecordsIsEmptyBeforeAnythingIsRecorded(): void
    {
        self::assertSame([], (new StatementRecorder())->records());
    }
}
