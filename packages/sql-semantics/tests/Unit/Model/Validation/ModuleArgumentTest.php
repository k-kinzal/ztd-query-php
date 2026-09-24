<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\ModuleArgument;

#[CoversClass(ModuleArgument::class)]
#[Medium]
final class ModuleArgumentTest extends TestCase
{
    #[TestWith(['title'])]
    #[TestWith(['tokenize = "porter ascii"'])]
    #[TestWith(['content=\'\''])]
    public function testCheckAcceptsOneCompleteArgument(string $text): void
    {
        ModuleArgument::check($text);
        $this->addToAssertionCount(1);
    }

    #[TestWith(['title, body'])]
    #[TestWith([''])]
    #[TestWith([' title'])]
    #[TestWith(['title); SELECT 1; --'])]
    public function testCheckRejectsAnythingButExactlyOneArgument(string $text): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A constructor argument must contain exactly one nonempty module argument.');
        ModuleArgument::check($text);
    }

    #[TestWith(['title)'])]
    #[TestWith(['(title'])]
    public function testCheckRejectsEscapingTheArgumentBoundary(string $text): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A module argument must remain within its SQL argument boundary.');
        ModuleArgument::check($text);
    }

    public function testTextExcludesTheTriviaAroundEachArgument(): void
    {
        $source = (new DialectParser(Dialect::Sqlite))->parse('CREATE VIRTUAL TABLE d USING m(  title   ,  tokenize = "porter" )');
        $arguments = Tree::outer($source, ['vtabarg']);
        self::assertSame(['title', 'tokenize = "porter"'], array_map(ModuleArgument::text(...), $arguments));
    }

    public function testCheckWrapsALexicalErrorWithoutACode(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A module argument must remain within its SQL argument boundary.');
        $this->expectExceptionCode(0);
        ModuleArgument::check("'title");
    }
}
