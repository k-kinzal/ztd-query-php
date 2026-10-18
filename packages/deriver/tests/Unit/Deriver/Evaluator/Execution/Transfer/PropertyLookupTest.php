<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Transfer;

use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\ConstantSignatures;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\Hierarchy\Builtins;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup
 */
#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(PropertyLookup::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[Small]
final class PropertyLookupTest extends TestCase
{
    public function testFindPrefersTheLexicalPrivateSlotInASubclass(): void
    {
        $source = '<?php class A{private $x;}class B extends A{public $x;}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $program = new Index($snapshotId, $trees);
        $lookup = new PropertyLookup($program);
        self::assertSame('A', $lookup->find('B', 'A', 'x')?->className);
        self::assertSame('B', $lookup->find('B', '', 'x')?->className);
    }
    public function testAccessibleAllowsProtectedAccessFromRelatedClasses(): void
    {
        $source = '<?php class A{protected $x;}class B extends A{}';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $program = new Index($snapshotId, $trees);
        $lookup = new PropertyLookup($program);
        $property = $lookup->find('B', '', 'x');
        self::assertTrue($lookup->accessible($property, 'B'));
        self::assertFalse($lookup->accessible($property, 'Unrelated'));
    }
}
