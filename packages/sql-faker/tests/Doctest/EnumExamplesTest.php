<?php

declare(strict_types=1);

namespace Tests\Doctest\SqlFaker;

use Generator;
use LogicException;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Toolkit\Doctest\Executor\ExampleExecutor;
use Toolkit\Doctest\Parser\Example;
use Toolkit\Doctest\Parser\ExampleExtractor;
use Toolkit\Doctest\Scanner\ParserFactoryBridge;
use Toolkit\Doctest\Scanner\Target;
use Toolkit\Doctest\Scanner\TargetKind;

/**
 * Supplies enum PHPDoc to the toolkit until its source scanner collects enums and cases.
 *
 * Extraction, assertions and execution use the same toolkit as DoctestSuite.
 * This bridge fails on missing documentation instead of silently yielding no tests.
 * It can be removed once the built-in suite discovers these declarations.
 */
#[CoversNothing]
#[Medium]
final class EnumExamplesTest extends TestCase
{
    /**
     * Execute the assertions extracted from a public enum declaration.
     *
     * @param Example $example Documented consumer behavior
     */
    #[DataProvider('providerExamples')]
    public function testExample(Example $example): void
    {
        $result = (new ExampleExecutor())->execute($example);

        self::assertTrue($result->passed, $result->getErrorMessage());
    }

    /**
     * Read the public statement enums and their cases as doctest targets.
     *
     * @return Generator<string, array{Example}>
     * @throws LogicException When an enum or case has no executable documentation
     * @throws \PhpParser\Error When an enum source cannot be parsed
     */
    public static function providerExamples(): Generator
    {
        foreach (['MySql', 'PostgreSql', 'Sqlite'] as $dialect) {
            $file = __DIR__ . '/../../src/' . $dialect . '/StatementRule.php';
            $source = file_get_contents($file);
            if ($source === false) {
                throw new LogicException('Cannot read statement enum: ' . $file);
            }
            $nodes = (new ParserFactoryBridge())->create()->parse($source) ?? [];
            $enums = (new NodeFinder())->findInstanceOf($nodes, Enum_::class);
            if (count($enums) !== 1) {
                throw new LogicException('Expected one public statement enum in ' . $file);
            }
            foreach ($enums as $enum) {
                $declarations = ['StatementRule' => $enum];
                foreach ((new NodeFinder())->findInstanceOf($enum->stmts, \PhpParser\Node\Stmt\EnumCase::class) as $case) {
                    $declarations['StatementRule::' . $case->name->toString()] = $case;
                }
                foreach ($declarations as $name => $declaration) {
                    $docblock = $declaration->getDocComment();
                    if ($docblock === null) {
                        throw new LogicException('Missing PHPDoc for ' . $dialect . '::' . $name);
                    }
                    $target = new Target(
                        TargetKind::CLASS_LIKE,
                        $file,
                        $docblock->getText(),
                        $name,
                        $docblock->getStartLine(),
                        'SqlFaker\\' . $dialect,
                    );
                    $examples = iterator_to_array((new ExampleExtractor())->extract($target));
                    if ($examples === []) {
                        throw new LogicException('Missing executable example for ' . $dialect . '::' . $name);
                    }
                    foreach ($examples as $example) {
                        yield $example->target->getFullyQualifiedName() . ' ' . $example->getName() => [$example];
                    }
                }
            }
        }
    }
}
