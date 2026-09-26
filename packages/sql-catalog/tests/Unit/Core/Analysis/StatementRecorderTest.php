<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\QueryRecord;
use SqlCatalog\Core\Analysis\StatementRecorder;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextPattern;

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

    public function testRecordKeepsAStatementUncombinedAndWholeUnlessToldOtherwise(): void
    {
        $recorder = new StatementRecorder();
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');

        $plain = $recorder->record($site, 'a.php:0:pdo.query', TextPattern::fromText('SELECT 1'));
        $marked = $recorder->record($site, 'a.php:0:pdo.query', TextPattern::fromText('SELECT 1'), null, true, ['g'], true);

        self::assertFalse($plain->combined);
        self::assertFalse($plain->isTruncated());
        self::assertTrue($marked->combined);
        self::assertTrue($marked->isTruncated());
        self::assertSame(['g'], $marked->through);
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
