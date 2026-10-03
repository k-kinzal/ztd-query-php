<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\Identifier as Subject;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\Node;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
final class IdentifierTest extends TestCase
{
    public function testDecodeReadsPlainQuotedAndKeywordIdentifiers(): void
    {
        $tree = (new MySqlParser())->parse('CREATE TABLE `my``table` (plain INT, `quo``ted` INT, name INT, `猫` INT)');
        $idents = $tree->find('ident');

        self::assertSame(['my`table', 'plain', 'quo`ted', 'name', '猫'], array_map(static fn (Node $ident): ?string => (new Subject())->decode($ident), $idents));
    }

    public function testDecodeReturnsNullForAnEmptyNode(): void
    {
        self::assertNull((new Subject())->decode(new Node('ident', 0, [])));
    }
}
