<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Upsert\AssignmentReader;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(AssignmentReader::class)]
final class AssignmentReaderTest extends TestCase
{
    public function testAssignmentExtractsTheLastQualifiedIdentifier(): void
    {
        $reader = new AssignmentReader();
        self::assertSame(['column' => 'score', 'value' => 'VALUES(score) + 1'], $reader->assignment('`users`.`score` = VALUES(score) + 1'));
        self::assertNull($reader->assignment('score = '));
        self::assertNull($reader->assignment('score'));
    }

    public function testLastIdentifierDecodesEscapedNames(): void
    {
        self::assertSame('a`b', (new AssignmentReader())->lastIdentifier(\ZtdQuery\Sql\SqlTokenStream::tokenize('users.`a``b`', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens()));
        self::assertNull((new AssignmentReader())->lastIdentifier([]));
    }

}
