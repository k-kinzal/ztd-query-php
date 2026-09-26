<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Php;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Php\ParsedFile;
use SqlCatalog\Core\Php\SourceParser;
use SqlCatalog\Core\Php\SyntaxException;

#[CoversClass(SourceParser::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(SyntaxException::class)]
final class SourceParserTest extends TestCase
{
    public function testParseReturnsTheStatementsOfTheFile(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php $a = 1;');
        self::assertSame('a.php', $file->path);
        self::assertCount(1, $file->statements);
    }

    public function testParseResolvesNamesToTheirFullyQualifiedForm(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php namespace App; class User {}');
        $namespace = $file->statements[0];
        self::assertInstanceOf(Namespace_::class, $namespace);
        $class = $namespace->stmts[0];
        self::assertInstanceOf(Class_::class, $class);
        self::assertSame('App\\User', $class->namespacedName?->toString());
    }

    public function testParseReportsTheFileASyntaxErrorCameFrom(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Cannot parse "broken.php"');
        (new SourceParser())->parse('broken.php', '<?php function {');
    }

    public function testResolveNamesKeepsOnlyStatements(): void
    {
        self::assertCount(1, (new SourceParser())->resolveNames([new Nop()]));
    }

    public function testResolveNamesConnectsFileScopeSiblingsForWriteAnalysis(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php $a =& $b; $b = 1;');
        self::assertSame($file->statements, $file->statements[1]->getAttribute('fileStatements'));
    }

}
