<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint\FullText;

use MySqlMemory\Command\Definition\Constraint\FullText\History;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(History::class)]
#[Small]
final class HistoryTest extends TestCase
{
    public function testChangedKeepsTheDocumentIdentityAfterDroppingItsIndex(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a TEXT); CREATE FULLTEXT INDEX idx ON t(a)');
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([['Warning', '124', 'InnoDB rebuilding table to add column FTS_DOC_ID']], $warnings->rows);
        $session->query('ALTER TABLE t DROP INDEX idx; CREATE FULLTEXT INDEX idx ON t(a)');
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
        self::assertTrue($session->instance->dictionary->table('d', 't')?->fullTextDocumentId);
    }
}
