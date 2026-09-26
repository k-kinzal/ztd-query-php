<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Effect;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Effect\WriteEffects;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Evaluation\Presence;
use SqlCatalog\Core\Php\SourceParser;

#[CoversClass(WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(Environment::class)]
#[UsesClass(Domain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Core\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Core\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParameterShape::class)]
final class WriteEffectsTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerWrites')]
    public function testOwnRecognizesUnmodelledWrites(string $source, array $expected): void
    {
        $statement = (new SourceParser())->parse('a.php', '<?php ' . $source . ';')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertSame($expected, array_keys((new WriteEffects())->own($statement->expr)));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function providerWrites(): array
    {
        return [
            'include' => ['include "a.php"', ['*']],
            'eval' => ['eval($code)', ['*']],
            'dynamic assignment' => ['$$name = "x"', ['*']],
            'ordinary assignment' => ['$a = "x"', []],
            'reference' => ['$a =& $b', ['a', 'b']],
            'extract' => ['extract($data)', ['*']],
            'unknown call' => ['unknown($a)', ['a']],
            'first class callable' => ['unknown(...)', []],
        ];
    }

    public function testArgumentsRetainsWritableArgumentsOnly(): void
    {
        $statement = (new SourceParser())->parse('a.php', '<?php unknown($a, "constant", $items[0]);')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(Expr\FuncCall::class, $statement->expr);
        self::assertSame(['a' => true, 'items' => true], (new WriteEffects())->arguments($statement->expr));
    }

    public function testApplyInvalidatesAbsenceAndValuesWithoutChangingUnrelatedBindings(): void
    {
        $environment = new Environment(['keep' => Domain::literal('known')]);
        $environment->markAbsent('tail');
        (new WriteEffects())->apply(['tail' => true], $environment);
        self::assertSame(Presence::Maybe, $environment->presence('tail'));
        self::assertNull($environment->read('tail')->soleLiteral());
        self::assertSame('known', $environment->read('keep')->soleLiteral()?->value);
        (new WriteEffects())->apply(['*' => true], $environment);
        self::assertNull($environment->read('keep')->soleLiteral());
    }
    public function testDynamicTargetFindsNestedVariableVariables(): void
    {
        $statement = (new SourceParser())->parse('a.php', '<?php [$$name[0]] = $data;')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(Expr\Assign::class, $statement->expr);
        self::assertSame(['*' => true], (new WriteEffects())->dynamicTarget($statement->expr->var));
        self::assertSame([], (new WriteEffects())->dynamicTarget(new Expr\Variable('name')));
    }

    public function testAssignmentInvalidatesAliasValuesBeforeWritingTheTarget(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php $a =& $b; $b = "new";');
        $statement = $file->statements[1];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(Expr\Assign::class, $statement->expr);
        $environment = new Environment(['a' => Domain::literal('old'), 'b' => Domain::literal('old')]);
        (new WriteEffects())->assignment($statement->expr->var, $environment);
        self::assertSame(Presence::Maybe, $environment->presence('a'));
        self::assertNull($environment->read('a')->soleLiteral());
    }


    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerReferenceArguments')]
    public function testArgumentsUsesReferenceDeclarationsAndNamedArguments(string $call, array $expected): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function change($keep, &$out) {} ' . $call . ';');
        $index = (new \SqlCatalog\Core\Php\ProgramIndexBuilder())->build([$file]);
        $statement = $file->statements[1];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(Expr\CallLike::class, $statement->expr);
        self::assertSame($expected, array_keys((new WriteEffects())->arguments($statement->expr, $index)));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function providerReferenceArguments(): array
    {
        return [
            'positional' => ['change($a, $b)', ['b']],
            'named' => ['change(out: $b, keep: $a)', ['b']],
            'unpacked' => ['change(...$a)', ['a']],
            'unknown signature' => ['unknown($a)', ['a']],
            'static method' => ['Other::change($a)', ['a']],
        ];
    }

}
