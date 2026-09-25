<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\EntryIdentity;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextPattern;

#[CoversClass(EntryIdentity::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class EntryIdentityTest extends TestCase
{
    public function testComputeIsStableForTheSameStatement(): void
    {
        $identity = new EntryIdentity();
        $site = new CallSite('a.php', 3, 'f', 'pdo.query');
        self::assertSame(
            $identity->compute($site, TextPattern::fromText('SELECT 1')),
            $identity->compute($site, TextPattern::fromText('SELECT 1')),
        );
    }

    public function testComputeIgnoresTheLineNumber(): void
    {
        $identity = new EntryIdentity();
        self::assertSame(
            $identity->compute(new CallSite('a.php', 3, 'f', 'pdo.query'), TextPattern::fromText('SELECT 1')),
            $identity->compute(new CallSite('a.php', 90, 'f', 'pdo.query'), TextPattern::fromText('SELECT 1')),
        );
    }

    public function testComputeSeparatesDifferentStatementsAndSites(): void
    {
        $identity = new EntryIdentity();
        $site = new CallSite('a.php', 3, 'f', 'pdo.query');
        self::assertNotSame(
            $identity->compute($site, TextPattern::fromText('SELECT 1')),
            $identity->compute($site, TextPattern::fromText('SELECT 2')),
        );
        self::assertNotSame(
            $identity->compute($site, TextPattern::fromText('SELECT 1')),
            $identity->compute(new CallSite('b.php', 3, 'f', 'pdo.query'), TextPattern::fromText('SELECT 1')),
        );
    }

    public function testComputeSeparatesStatementsItCannotOtherwiseTellApart(): void
    {
        $identity = new EntryIdentity();
        $site = new CallSite('a.php', 3, 'f', 'unreached');

        self::assertNotSame(
            $identity->compute($site, TextPattern::fromText('SELECT 1')),
            $identity->compute($site, TextPattern::fromText('SELECT 1'), 1),
        );
    }

    public function testComputeIsAsLongAsItSaysItIs(): void
    {
        $computed = (new EntryIdentity())->compute(
            new CallSite('a.php', 3, 'f', 'pdo.query'),
            TextPattern::fromText('SELECT 1'),
        );
        self::assertSame(EntryIdentity::LENGTH, strlen($computed));
    }
}
