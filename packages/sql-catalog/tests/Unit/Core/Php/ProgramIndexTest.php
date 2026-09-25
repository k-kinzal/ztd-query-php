<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Php;

use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Php\ClassShape;
use SqlCatalog\Core\Php\FunctionShape;
use SqlCatalog\Core\Php\MethodShape;
use SqlCatalog\Core\Php\ParsedFile;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Php\ProgramIndexBuilder;
use SqlCatalog\Core\Php\SourceParser;
use SqlCatalog\Core\Php\TypeReader;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(ProgramIndex::class)]
#[UsesClass(ClassShape::class)]
#[UsesClass(FunctionShape::class)]
#[UsesClass(MethodShape::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(TypeReader::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParameterShape::class)]
final class ProgramIndexTest extends TestCase
{
    public function testFindClassIgnoresCaseAndLeadingBackslashes(): void
    {
        $index = new ProgramIndex(['app\\user' => new ClassShape('App\\User', null, [], [], false, [], [], [], [])]);
        self::assertSame('App\\User', $index->findClass('\\APP\\User')?->name);
        self::assertNull($index->findClass(null));
        self::assertNull($index->findClass('App\\Missing'));
    }

    public function testFindFunctionIgnoresCase(): void
    {
        $index = new ProgramIndex([], ['app\\find' => new FunctionShape('App\\find', [], TypeShape::unknown())]);
        self::assertSame('App\\find', $index->findFunction('App\\Find')?->name);
        self::assertNull($index->findFunction('App\\missing'));
    }

    public function testFindConstantReadsTheDeclaredExpression(): void
    {
        $value = new String_('users');
        $index = new ProgramIndex([], [], ['App\\TABLE' => $value]);
        self::assertSame($value, $index->findConstant('\\App\\TABLE'));
        self::assertNull($index->findConstant('App\\MISSING'));
    }

    public function testFindMethodFollowsTheParentClass(): void
    {
        $index = (new ProgramIndexBuilder())->build([(new SourceParser())->parse(
            'a.php',
            '<?php abstract class Base { public function run(): string { return "x"; } } class Child extends Base {}',
        )]);
        self::assertSame('Base::run', $index->findMethod('Child', 'run')?->qualifiedName());
        self::assertNull($index->findMethod('Child', 'missing'));
    }

    public function testFindClassConstantFollowsTheParentClass(): void
    {
        $index = (new ProgramIndexBuilder())->build([(new SourceParser())->parse(
            'a.php',
            '<?php class Base { public const TABLE = "users"; } class Child extends Base {}',
        )]);
        self::assertInstanceOf(String_::class, $index->findClassConstant('Child', 'TABLE'));
        self::assertNull($index->findClassConstant('Child', 'MISSING'));
    }

    public function testFindPropertyTypeFollowsTheParentClass(): void
    {
        $index = (new ProgramIndexBuilder())->build([(new SourceParser())->parse(
            'a.php',
            '<?php class Base { protected string $order = "id"; } class Child extends Base {}',
        )]);
        self::assertSame('string', $index->findPropertyType('Child', 'order')?->display());
        self::assertNull($index->findPropertyType('Child', 'missing'));
    }

    public function testIsInstanceOfFollowsParentsAndInterfaces(): void
    {
        $index = (new ProgramIndexBuilder())->build([(new SourceParser())->parse(
            'a.php',
            '<?php interface Runs {} class Base implements Runs {} class Child extends Base {}',
        )]);
        self::assertTrue($index->isInstanceOf('Child', 'Base'));
        self::assertTrue($index->isInstanceOf('Child', '\\Runs'));
        self::assertTrue($index->isInstanceOf('Child', 'Child'));
        self::assertFalse($index->isInstanceOf('Child', 'Other'));
        self::assertFalse($index->isInstanceOf(null, 'Base'));
    }

    public function testLineageVisitsEachDeclarationOnce(): void
    {
        $index = (new ProgramIndexBuilder())->build([(new SourceParser())->parse(
            'a.php',
            '<?php interface Runs {} class Base implements Runs {} class Child extends Base implements Runs {}',
        )]);
        self::assertSame(['Child', 'Base', 'Runs'], array_map(
            static fn (ClassShape $shape): string => $shape->name,
            $index->lineage('Child'),
        ));
        self::assertSame([], $index->lineage('Missing'));
    }

    public function testImplementationsOfCollectsTheConcreteBodies(): void
    {
        $index = (new ProgramIndexBuilder())->build([(new SourceParser())->parse(
            'a.php',
            '<?php abstract class Base { abstract public function table(): string; }'
            . ' class Users extends Base { public function table(): string { return "users"; } }'
            . ' class Orders extends Base { public function table(): string { return "orders"; } }',
        )]);
        self::assertCount(2, $index->implementationsOf('Base', 'table'));
        self::assertSame([], $index->implementationsOf(null, 'table'));
        self::assertSame([], $index->implementationsOf('Base', 'table', 1));
    }

    public function testMergePrefersTheDeclarationsOfThisIndex(): void
    {
        $left = new ProgramIndex(['a' => new ClassShape('A', null, [], [], false, [], [], [], [])]);
        $right = new ProgramIndex([
            'a' => new ClassShape('Other', null, [], [], false, [], [], [], []),
            'b' => new ClassShape('B', null, [], [], false, [], [], [], []),
        ]);
        $merged = $left->merge($right);
        self::assertSame('A', $merged->findClass('A')?->name);
        self::assertSame('B', $merged->findClass('B')?->name);
    }
}
