<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownStorage;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UnknownStorage::class)]
#[Small]
final class UnknownStorageTest extends TestCase
{
    public function testMessageSpellsTheWordAsWritten(): void
    {
        self::assertSame('Generated column storage PERSISTED is neither VIRTUAL nor STORED.', (new UnknownStorage(new Word(new Name('PERSISTED'))))->message());
    }
}
