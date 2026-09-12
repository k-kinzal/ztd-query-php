<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\Partition\SourceProjection;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SelectionReader::class)]
#[CoversClass(SourceProjection::class)]
final class SourceProjectionTest extends TestCase
{
    public function testEdit(): void
    {
        $sql = 'db.t PARTITION (p0)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $reference = ['name' => 't', 'start' => 0, 'unqualifiedStart' => 3, 'end' => 4];
        self::assertSame(['start' => 0, 'end' => 19, 'replacement' => '(SELECT * FROM t WHERE id < 10) AS t'], (new SourceProjection())->edit($sql, $reference, $tokens, 6, 'id < 10'));
    }

    public function testEditPreservesExistingAlias(): void
    {
        $sql = 't PARTITION (p0) alias';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $reference = ['name' => 't', 'start' => 0, 'unqualifiedStart' => 0, 'end' => 1];
        self::assertSame(['start' => 0, 'end' => 16, 'replacement' => '(SELECT * FROM t WHERE TRUE)'], (new SourceProjection())->edit($sql, $reference, $tokens, 4, 'TRUE'));
    }

}
