<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
final class GrammarTest extends TestCase
{
    public function testConstructor(): void
    {
        $rule = new ProductionRule('start', [new Production([new Terminal('A')])]);
        $grammar = new Grammar('start', ['start' => $rule]);

        self::assertSame('start', $grammar->startSymbol);
        self::assertSame(['start' => $rule], $grammar->ruleMap);
    }

    public function testConstructorKeyLhsMismatchThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Grammar('start', ['wrong_key' => new ProductionRule('start', [])]);
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
        $serialized = serialize(new \stdClass());
        file_put_contents($tmpFile, "<?php return ['key' => '" . $serialized . "'];");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to load grammar from');

        try {
            Grammar::loadFromFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }
}
