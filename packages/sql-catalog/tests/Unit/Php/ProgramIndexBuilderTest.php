<?php

declare(strict_types=1);

namespace Tests\Unit\Php;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Php\ClassShape;
use SqlCatalog\Php\FunctionShape;
use SqlCatalog\Php\MethodShape;
use SqlCatalog\Php\ParameterShape;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Type\TypeShape;

#[CoversClass(ProgramIndexBuilder::class)]
#[UsesClass(ClassShape::class)]
#[UsesClass(FunctionShape::class)]
#[UsesClass(MethodShape::class)]
#[UsesClass(ParameterShape::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(TypeReader::class)]
#[UsesClass(TypeShape::class)]
final class ProgramIndexBuilderTest extends TestCase
{
    public function testBuildIndexesClassesFunctionsAndConstants(): void
    {
        $file = (new SourceParser())->parse(
            'a.php',
            '<?php namespace App; const TABLE = "users"; function find(): void {} class User {}',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        self::assertSame('App\\User', $index->findClass('App\\User')?->name);
        self::assertSame('App\\find', $index->findFunction('App\\find')?->name);
        self::assertInstanceOf(String_::class, $index->findConstant('App\\TABLE'));
    }

    public function testReadClassCollectsConstantsPropertiesAndMethods(): void
    {
        $file = (new SourceParser())->parse(
            'a.php',
            '<?php class User { public const TABLE = "users"; private string $order = "id";'
            . ' public function __construct(private \\PDO $pdo) {} public function find(): void {} }',
        );
        $node = (new NodeFinder())->findFirstInstanceOf($file->statements, Class_::class);
        self::assertInstanceOf(Class_::class, $node);
        $shape = (new ProgramIndexBuilder())->readClass($node, 'a.php');
        self::assertNotNull($shape);
        self::assertArrayHasKey('TABLE', $shape->constants);
        self::assertSame('string', $shape->propertyTypes['order']->display());
        self::assertSame('PDO', $shape->propertyTypes['pdo']->display());
        self::assertArrayHasKey('find', $shape->methods);
        self::assertSame('a.php', $shape->methods['find']->file);
    }

    public function testReadClassRecordsEnumCases(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php enum Status: string { case Active = "active"; }');
        $node = (new NodeFinder())->findFirstInstanceOf($file->statements, Enum_::class);
        self::assertInstanceOf(Enum_::class, $node);
        $shape = (new ProgramIndexBuilder())->readClass($node);
        self::assertNotNull($shape);
        self::assertTrue($shape->enum);
        self::assertArrayHasKey('Active', $shape->enumCases);
    }

    public function testReadClassIsNullForAnAnonymousClass(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php $a = new class {};');
        $node = (new NodeFinder())->findFirstInstanceOf($file->statements, Class_::class);
        self::assertInstanceOf(Class_::class, $node);
        self::assertNull((new ProgramIndexBuilder())->readClass($node));
    }

    public function testReadParentNamesCollectsImplementedInterfaces(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php interface A {} class B implements A {}');
        $node = (new NodeFinder())->findFirstInstanceOf($file->statements, Class_::class);
        self::assertInstanceOf(Class_::class, $node);
        self::assertSame(['A'], (new ProgramIndexBuilder())->readParentNames($node));
    }

    public function testReadParentNamesCollectsExtendedInterfaces(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php interface A {} interface B extends A {}');
        $nodes = (new NodeFinder())->findInstanceOf($file->statements, Interface_::class);
        self::assertSame(['A'], (new ProgramIndexBuilder())->readParentNames($nodes[1]));
    }

    public function testReadTraitNamesCollectsUsedTraits(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php trait T {} class B { use T; }');
        $node = (new NodeFinder())->findFirstInstanceOf($file->statements, Class_::class);
        self::assertInstanceOf(Class_::class, $node);
        self::assertSame(['T'], (new ProgramIndexBuilder())->readTraitNames($node));
    }

    public function testReadConstantsCollectsEveryDeclaration(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php class B { public const A = 1, C = 2; }');
        $node = (new NodeFinder())->findFirstInstanceOf($file->statements, Class_::class);
        self::assertInstanceOf(Class_::class, $node);
        self::assertSame(['A', 'C'], array_keys((new ProgramIndexBuilder())->readConstants($node)));
    }

    public function testReadEnumCasesKeepsACaseWithoutABackingValue(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php enum Status { case Active; }');
        $node = (new NodeFinder())->findFirstInstanceOf($file->statements, Enum_::class);
        self::assertInstanceOf(Enum_::class, $node);
        self::assertSame(['Active' => null], (new ProgramIndexBuilder())->readEnumCases($node));
    }

    public function testReadPromotedPropertiesOnlyLooksAtTheConstructor(): void
    {
        $file = (new SourceParser())->parse(
            'a.php',
            '<?php class B { public function __construct(private \\PDO $pdo) {} public function other(int $x): void {} }',
        );
        $methods = (new NodeFinder())->findInstanceOf($file->statements, ClassMethod::class);
        $builder = new ProgramIndexBuilder();
        self::assertSame(['pdo'], array_keys($builder->readPromotedProperties($methods[0])));
        self::assertSame([], $builder->readPromotedProperties($methods[1]));
    }

    public function testReadMethodRecordsTheSignature(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php class B { public static function find(int $id): string { return ""; } }');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, ClassMethod::class);
        self::assertInstanceOf(ClassMethod::class, $method);
        $shape = (new ProgramIndexBuilder())->readMethod('B', $method, 'a.php');
        self::assertTrue($shape->static);
        self::assertSame('string', $shape->returnType->display());
        self::assertSame('id', $shape->parameters[0]->name);
    }

    public function testReadParametersSkipsParametersWithoutAPlainName(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function f(int $a, string ...$rest): void {}');
        $function = (new NodeFinder())->findFirstInstanceOf($file->statements, \PhpParser\Node\Stmt\Function_::class);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $function);
        $parameters = (new ProgramIndexBuilder())->readParameters($function->params);
        self::assertCount(2, $parameters);
        self::assertTrue($parameters[1]->variadic);
    }

    public function testReadAssignedPropertiesFindsWritesInTheClassBody(): void
    {
        $file = (new SourceParser())->parse(
            'a.php',
            '<?php class B { private string $order = "id"; public function set(): void { $this->order = "name"; } }',
        );
        $node = (new NodeFinder())->findFirstInstanceOf($file->statements, Class_::class);
        self::assertInstanceOf(Class_::class, $node);
        self::assertSame(['order' => true], (new ProgramIndexBuilder())->readAssignedProperties($node));
    }

    public function testAssignmentTargetIsNullWhenNothingIsWrittenToAProperty(): void
    {
        $assign = new Assign(new Variable('a'), new String_('x'));
        self::assertNull((new ProgramIndexBuilder())->assignmentTarget($assign));
    }
}
