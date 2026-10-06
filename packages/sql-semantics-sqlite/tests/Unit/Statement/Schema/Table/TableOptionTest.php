<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOption;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOptionKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TableOption::class)]
#[Small]
final class TableOptionTest extends TestCase
{
    public function testKindReadsTheTwoKnownOptionsWithoutRegardToCase(): void
    {
        self::assertSame(TableOptionKind::WithoutRowid, (new TableOption(new Word(new Name('RowId')), true))->kind());
        self::assertSame(TableOptionKind::Strict, (new TableOption(new Word(new Name('strict'))))->kind());
    }

    public function testKindIsNullForEveryOtherWordAndForAQuotedWord(): void
    {
        self::assertNull((new TableOption(new Word(new Name('strict')), true))->kind());
        self::assertNull((new TableOption(new Word(new Name('rowid'))))->kind());
        self::assertNull((new TableOption(new Word(new Name('rowid'), WordQuote::Double), true))->kind());
        self::assertNull((new TableOption(new Word(new Name('strict'), WordQuote::Single)))->kind());
    }

    public function testRenderWritesTheWordWithItsQuoting(): void
    {
        $lexical = new Lexical();
        $without = new Output(new Codec());
        (new TableOption(new Word(new Name('ROWID')), true))->render($without);
        $quoted = new Output(new Codec());
        (new TableOption(new Word(new Name('strict'), WordQuote::Bracket)))->render($quoted);

        self::assertSame('WITHOUT ROWID', $lexical->join($without->pieces()));
        self::assertSame('[strict]', $lexical->join($quoted->pieces()));
    }
}
