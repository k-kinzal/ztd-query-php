<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use stdClass;

#[CoversClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
final class GrammarTest extends TestCase
{
    public function testExposesTheStartSymbolAndRuleMap(): void
    {
        $rule = new ProductionRule('start', [new Production([new Terminal('A')])]);
        $grammar = new Grammar('start', ['start' => $rule]);

        self::assertSame('start', $grammar->startSymbol);
        self::assertSame(['start' => $rule], $grammar->ruleMap);
    }


    public function testLoadFromFile(): void
    {
        $grammar = Grammar::loadFromFile(__DIR__ . '/../../../resources/ast/pg-17.2.php');

        self::assertNotEmpty($grammar->ruleMap);
    }

    public function testLoadFromFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Grammar file not found');

        Grammar::loadFromFile('/nonexistent/path/grammar.php');
    }

    public function testLoadFromFileInvalidData(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'grammar_test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, '<?php return [];');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid grammar file');

        try {
            Grammar::loadFromFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testLoadFromFileFailedUnserialize(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'grammar_test_');
        self::assertNotFalse($tmpFile);
        $serialized = serialize(new stdClass());
        file_put_contents($tmpFile, "<?php return ['key' => '" . $serialized . "'];");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to load grammar from');

        try {
            Grammar::loadFromFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testIdentifiedAssignsOriginalOrdinalsBeforeFiltering(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([]), new Production([new Terminal('SELECT')])])]);
        $identified = $grammar->identified();
        self::assertSame(0, $identified->ruleMap['stmt']->alternatives[0]->ordinal);
        self::assertSame('stmt#1', $identified->ruleMap['stmt']->alternatives[1]->origin);
        self::assertEquals($identified, $identified->identified());
    }
    public function testIdentifiedPreservesTheSourceAndEveryExistingProductionIdentity(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [
            new Production([new Terminal('A')]), new Production([new Terminal('B')], 7, 'upstream#7'), new Production([]),
        ])]);
        $identified = $grammar->identified();
        self::assertSame('stmt', $identified->startSymbol);
        self::assertNull($grammar->ruleMap['stmt']->alternatives[0]->ordinal);
        self::assertSame([0, 7, 2], array_column($identified->ruleMap['stmt']->alternatives, 'ordinal'));
        self::assertSame(['stmt#0', 'upstream#7', 'stmt#2'], array_column($identified->ruleMap['stmt']->alternatives, 'origin'));
        self::assertSame($grammar->ruleMap['stmt']->alternatives[0]->symbols, $identified->ruleMap['stmt']->alternatives[0]->symbols);
        self::assertSame([], $identified->ruleMap['stmt']->alternatives[2]->symbols);
        self::assertEquals($identified, $identified->identified());
    }

}
