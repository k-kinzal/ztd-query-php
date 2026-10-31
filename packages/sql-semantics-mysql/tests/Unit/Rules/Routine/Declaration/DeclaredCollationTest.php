<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\DeclaredCollation;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

#[CoversClass(DeclaredCollation::class)]
#[Small]
final class DeclaredCollationTest extends TestCase
{
    public function testWarnRetainsAnExplicitDeprecatedCollation(): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context());
        $rule = new DeclaredCollation();
        $rule->warn(null, $derivation);
        $rule->warn(new CollationName(new Name('utf8mb4_bin')), $derivation);
        $rule->warn(new CollationName(new Name('ucs2_bin')), $derivation);

        $warnings = $derivation->facts()->warnings;

        self::assertCount(1, $warnings);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::class, $warnings[0]);
        self::assertSame(4079, $warnings[0]->code());
    }

    /**
     * @return iterable<string, array{Closure(): Node}>
     */
    public static function providerDeclarations(): iterable
    {
        $collation = new CollationName(new Name('utf8mb4_bin'));
        foreach ([new Integral(IntegralKind::Int), new Character(CharacterKind::VarChar, '3'), new Character(CharacterKind::Char, '3', charset: new CharsetAttribute(CharsetForm::Binary))] as $index => $type) {
            yield $index . ': parameter' => [static fn (): Node => new Parameter(new Name('x'), $type, $collation)];
            yield $index . ': variable' => [static fn (): Node => new VariableDeclaration([new Name('x')], $type, $collation)];
            yield $index . ': return' => [static fn (): Node => new CreateFunction(new QualifiedName(new Name('f')), new ParameterList([]), $type, new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new ReturnStatement(new NullLiteral())), $collation)];
        }
    }

    /**
     * @param Closure(): Node $construct A declaration that cannot supply its collation's character set
     */
    #[DataProvider('providerDeclarations')]
    public function testCheckRejectsAnOverrideWithoutADeclaredCharacterSet(Closure $construct): void
    {
        $this->expectExceptionMessage('COLLATE with no CHARACTER SET in SP parameters, RETURNS, DECLARE');

        $construct();
    }

    public function testCheckRejectsKnownIncompatibleCharacterSets(): void
    {
        $this->expectExceptionMessage("COLLATION 'utf8mb4_bin' is not valid for CHARACTER SET 'binary'");

        (new DeclaredCollation())->check(new Binary(BinaryKind::VarBinary, '3'), new CollationName(new Name('utf8mb4_bin')));
    }

    public function testProblemKeepsLegacyDefaultWithoutACharacterSet(): void
    {
        self::assertNull((new DeclaredCollation())->problem(new Integral(IntegralKind::Int), new CollationName(null)));
        self::assertNull((new DeclaredCollation())->problem(new Binary(BinaryKind::VarBinary, '3'), new CollationName(new Name('binary'))));
    }

    public function testTypeSuppliesTheDeclaredCollationToReferenceTyping(): void
    {
        $context = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context();
        $type = new Character(CharacterKind::VarChar, '3', charset: new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4')));
        $resolved = (new DeclaredCollation())->type($type, new CollationName(new Name('utf8mb4_bin')), $context);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $resolved);
        self::assertSame('utf8mb4_bin', $resolved->collation->name);
        self::assertSame($type, (new DeclaredCollation())->type($type, null, $context));
    }

    /**
     * @return iterable<string, array{DeclaredName, ?string}>
     */
    public static function providerTypes(): iterable
    {
        yield 'numeric' => [new Integral(IntegralKind::Int), null];
        yield 'ordinary text' => [new Character(CharacterKind::Char), null];
        yield 'binary attribute' => [new Character(CharacterKind::Char, charset: new CharsetAttribute(CharsetForm::Binary)), null];
        yield 'national' => [new Character(CharacterKind::Char, national: true), 'utf8mb3'];
        yield 'bytes' => [new Binary(BinaryKind::VarBinary, '3'), 'binary'];
        yield 'json' => [new Elementary(ElementaryKind::Json), 'binary'];
        yield 'vector' => [new Elementary(ElementaryKind::Vector, '3'), 'binary'];
        foreach ([CharsetForm::Ascii->name => 'latin1', CharsetForm::Unicode->name => 'ucs2', CharsetForm::Byte->name => 'binary'] as $form => $charset) {
            $attribute = match ($form) {
                'Ascii' => CharsetForm::Ascii, 'Unicode' => CharsetForm::Unicode, 'Byte' => CharsetForm::Byte
            };
            yield $form => [new Character(CharacterKind::Char, charset: new CharsetAttribute($attribute)), $charset];
        }
        yield 'named' => [new Character(CharacterKind::Char, charset: new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4'))), 'utf8mb4'];
    }

    #[DataProvider('providerTypes')]
    public function testCharsetReadsOnlyTheDeclaredType(DeclaredName $type, ?string $expected): void
    {
        self::assertSame($expected, (new DeclaredCollation())->charset($type));
    }
}
