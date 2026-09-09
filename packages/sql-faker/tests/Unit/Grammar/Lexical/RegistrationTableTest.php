<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Lexical\RegistrationTable;

#[CoversClass(RegistrationTable::class)]
final class RegistrationTableTest extends TestCase
{
    public function testWithoutCommentsPreservesQuotedDelimiters(): void
    {
        $reader = new RegistrationTable();
        self::assertSame('"/*text*/"   "//text"  ', $reader->withoutComments('"/*text*/" /* comment */ "//text" // comment'));
        self::assertSame(' {"value"} ', $reader->body('static X rows[] = { {"value"} }; ignored;', 'rows'));
        self::assertSame([['{"value"}', 'value']], $reader->entries(' /*comment*/ {"value"}, ', '/\{"([a-z]+)"\}/'));
    }

    public function testBodyRejectsAnUnclosedRegion(): void
    {
        $this->expectException(RuntimeException::class);
        (new RegistrationTable())->body('static X rows[] = { {"value"}', 'rows');
    }

    public function testEntriesRejectsUnknownDeclarations(): void
    {
        $this->expectException(RuntimeException::class);
        (new RegistrationTable())->entries('{"value"}, UNKNOWN("lost")', '/\{"([a-z]+)"\}/');
    }
}
