<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Extension\Laravel\ModelMetadata;
use SqlCatalog\Extension\Laravel\QueryState;
use SqlCatalog\Php\ClassShape;
use SqlCatalog\Php\MethodShape;
use SqlCatalog\Php\ParameterShape;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(ModelMetadata::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextGeneralization::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(QueryState::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(ClassShape::class)]
#[UsesClass(MethodShape::class)]
#[UsesClass(ParameterShape::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(TypeReader::class)]
final class ModelMetadataTest extends TestCase
{
    public function testStateReadsInheritedTableKeyAndSoftDeletes(): void
    {
        $file = (new SourceParser())->parse('model.php', '<?php class Base extends \\Illuminate\\Database\\Eloquent\\Model { protected $table = "people"; protected $primaryKey = "uuid"; public $timestamps = false; } class User extends Base { use \\Illuminate\\Database\\Eloquent\\SoftDeletes; const DELETED_AT = "removed_at"; }');
        $model = new ModelMetadata((new ProgramIndexBuilder())->build([$file]));
        $state = $model->state('User', 'pgsql');
        self::assertSame('people', $state->string('table'));
        self::assertSame('people.uuid', $state->string('key'));
        self::assertSame('removed_at', $state->string('deletedColumn'));
        self::assertTrue($state->get('softDeletes')->soleLiteral()?->value);
        self::assertFalse($state->get('timestamps')->soleLiteral()?->value);
        self::assertArrayNotHasKey('problem', $state->fields);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerGuardLeavesHooksTraitsAndMutableMetadataOpen')]
    public function testGuardLeavesHooksTraitsAndMutableMetadataOpen(string $body): void
    {
        $file = (new SourceParser())->parse('model.php', '<?php class User extends \\Illuminate\\Database\\Eloquent\\Model { '.$body.' }');
        $model = new ModelMetadata((new ProgramIndexBuilder())->build([$file]));
        self::assertFalse($model->guard('User', new QueryState())->get('problem')->isExact());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function providerGuardLeavesHooksTraitsAndMutableMetadataOpen(): iterable
    {
        foreach (['protected static function booted() {}', 'use AppTrait;', 'public function rename() { $this->table = "other"; }', 'public function where() {}'] as $body) {
            yield [$body];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerGuardDefaultsRejectsUnknownMetadataAndImplicitEagerLoads')]
    public function testGuardDefaultsRejectsUnknownMetadataAndImplicitEagerLoads(string $body): void
    {
        $file = (new SourceParser())->parse('model.php', '<?php class User extends \\Illuminate\\Database\\Eloquent\\Model { '.$body.' }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $shape = $index->findClass('User');
        self::assertNotNull($shape);
        self::assertFalse((new ModelMetadata($index))->guardDefaults($shape, new QueryState())->get('problem')->isExact());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function providerGuardDefaultsRejectsUnknownMetadataAndImplicitEagerLoads(): iterable
    {
        foreach (['protected $table = UNKNOWN_TABLE;', 'protected $with = ["posts"];', 'protected $withCount = ["posts"];', 'const DELETED_AT = UNKNOWN_COLUMN;'] as $body) {
            yield [$body];
        }
    }

    public function testStateRejectsCustomizingAttributesAndUnknownTableConventions(): void
    {
        $file = (new SourceParser())->parse('model.php', '<?php #[CustomBuilder] class User extends \\Illuminate\\Database\\Eloquent\\Model {} class Fish extends \\Illuminate\\Database\\Eloquent\\Model {}');
        $model = new ModelMetadata((new ProgramIndexBuilder())->build([$file]));
        self::assertFalse($model->state('User', 'sqlite')->get('problem')->isExact());
        self::assertFalse($model->state('Fish', 'sqlite')->get('table')->isExact());
    }

    public function testRecognizesFrameworkAuthenticationModelsWithoutLoadingVendorCode(): void
    {
        $file = (new SourceParser())->parse('model.php', '<?php class User extends \\Illuminate\\Foundation\\Auth\\User {}');
        $model = new ModelMetadata((new ProgramIndexBuilder())->build([$file]));
        self::assertTrue($model->recognizes('User'));
        self::assertFalse($model->recognizes('Other'));
        self::assertFalse($model->recognizes(null));
    }

    public function testPropertyPrefersTheNearestDeclaration(): void
    {
        $file = (new SourceParser())->parse('model.php', '<?php class Base { protected $table = "old"; } class User extends Base { protected $table = "users"; }');
        $model = new ModelMetadata((new ProgramIndexBuilder())->build([$file]));
        self::assertSame('users', $model->property('User', 'table'));
        self::assertNull($model->property('User', 'missing'));
    }

    public function testConstantReadsOnlyLiteralDefaults(): void
    {
        $file = (new SourceParser())->parse('model.php', '<?php class User { const COLUMN = "removed_at"; }');
        $model = new ModelMetadata((new ProgramIndexBuilder())->build([$file]));
        self::assertSame('removed_at', $model->constant('User', 'COLUMN'));
        self::assertNull($model->constant('User', 'MISSING'));
    }

    public function testScalarDoesNotEvaluateApplicationCalls(): void
    {
        $model = new ModelMetadata(new ProgramIndex());
        self::assertSame('users', $model->scalar(new \PhpParser\Node\Scalar\String_('users')));
        self::assertNull($model->scalar(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('tableName'))));
    }

    public function testConventionalTableUsesKnownEnglishNamesAndRequiresExplicitOtherInflections(): void
    {
        $model = new ModelMetadata(new ProgramIndex());
        self::assertSame('admin_users', $model->conventionalTable('App\AdminUser'));
        self::assertNull($model->conventionalTable('App\Person'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerMetadataGaps')]
    public function testGuardExplainsTheSpecificUnmodelledModelEffect(string $body, string $expected): void
    {
        $file = (new SourceParser())->parse('model.php', '<?php class User extends \\Illuminate\\Database\\Eloquent\\Model { '.$body.' }');
        $model = new ModelMetadata((new ProgramIndexBuilder())->build([$file]));
        $term = $model->state('User', 'sqlite')->get('problem')->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $term);
        self::assertSame($expected, $term->expression);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function providerMetadataGaps(): iterable
    {
        yield ['use CustomTrait;', 'Unmodelled Eloquent trait: CustomTrait'];
        yield ['protected static function boot() {}', 'Unmodelled Eloquent override or boot hook: User::boot'];
        yield ['public function rename() { $this->table = "other"; }', 'Mutable Eloquent metadata: table'];
        yield ['protected $table = UNKNOWN_TABLE;', 'Unresolved Eloquent metadata: table'];
        yield ['protected $primaryKey = [];', 'Invalid Eloquent metadata: primaryKey'];
    }

    public function testStateKeepsStandardEmptyDefaultsAndFactoryTraitsClosed(): void
    {
        $file = (new SourceParser())->parse('model.php', '<?php class User extends \\Illuminate\\Database\\Eloquent\\Model { use \\Illuminate\\Database\\Eloquent\\Factories\\HasFactory; protected $with = []; protected $withCount = []; protected $table = null; }');
        $state = (new ModelMetadata((new ProgramIndexBuilder())->build([$file])))->state('User', 'sqlite');
        self::assertArrayNotHasKey('problem', $state->fields);
        self::assertSame('users', $state->string('table'));
        self::assertSame('users.id', $state->string('key'));
    }
}
