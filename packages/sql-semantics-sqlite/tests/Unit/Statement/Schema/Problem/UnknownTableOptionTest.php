<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownTableOption;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOption;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UnknownTableOption::class)]
#[Small]
final class UnknownTableOptionTest extends TestCase
{
    public function testMessageSpellsTheWordAsWritten(): void
    {
        self::assertSame('Table option "rowid" is unknown.', (new UnknownTableOption(new TableOption(new Word(new Name('rowid'), WordQuote::Double), true)))->message());
    }
}
