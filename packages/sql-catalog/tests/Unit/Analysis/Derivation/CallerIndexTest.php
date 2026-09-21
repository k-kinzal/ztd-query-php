<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\CallerIndex;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\SourceParser;

#[CoversClass(CallerIndex::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(SourceParser::class)]
final class CallerIndexTest extends TestCase
{
    public function testNamedFindsEveryCallWrittenWithTheName(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace App; find(1); $r->find(2); R::find(3); other();');
        $index = new CallerIndex([$file]);

        self::assertCount(3, $index->named('find'));
        self::assertCount(3, $index->named('\\App\\FIND'));
        self::assertSame([], $index->named('missing'));
    }

    public function testInstantiatingFindsEveryNewOfTheClass(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php new Repo(); new \\App\\Repo(); new $class();');

        self::assertCount(2, (new CallerIndex([$file]))->instantiating('Repo'));
    }

    public function testAddFilesACallUnderItsName(): void
    {
        $index = new CallerIndex();
        $index->add(new Expr\FuncCall(new Name('run')));
        $index->add(new Expr\FuncCall(new Expr\Variable('callable')));

        self::assertCount(1, $index->named('run'));
    }

    public function testNameOfReadsTheNameACallIsWrittenWith(): void
    {
        $index = new CallerIndex();

        self::assertSame('run', $index->nameOf(new Expr\FuncCall(new Name('run'))));
        self::assertSame('find', $index->nameOf(new Expr\MethodCall(new Expr\Variable('r'), 'find')));
        self::assertNull($index->nameOf(new Expr\MethodCall(new Expr\Variable('r'), new Expr\Variable('m'))));
        self::assertNull($index->nameOf(new Expr\New_(new Name('Repo'))));
    }

    public function testShortNameDropsTheNamespaceAndTheCase(): void
    {
        self::assertSame('find', (new CallerIndex())->shortName('\\App\\Repo\\Find'));
    }
}
