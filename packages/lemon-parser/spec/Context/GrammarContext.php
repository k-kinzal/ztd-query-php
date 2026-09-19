<?php

declare(strict_types=1);

namespace Spec\Context;

use function array_map;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

use function explode;

use LemonParser\Ast\GrammarFile;
use LemonParser\Parser;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;

use function trim;

/**
 * Steps of the specification: give a grammar file and, optionally, the names
 * defined for its %ifdef regions; parse it with the public parser; and state
 * either the whole resulting tree or the position and message of the error.
 */
final class GrammarContext implements Context
{
    private string $source = '';

    /**
     * @var list<string>
     */
    private array $defines = [];

    private ?GrammarFile $tree = null;

    private ?SyntaxException $failure = null;

    /**
     * Wires the parser under specification and the dumper that renders its trees.
     *
     * @param Parser $parser The parser every scenario runs
     * @param TreeDumper $dumper Renders a tree as the lines a scenario states
     */
    public function __construct(
        private readonly Parser $parser = new Parser(),
        private readonly TreeDumper $dumper = new TreeDumper(),
    ) {
    }

    /**
     * Takes the grammar file of the scenario, ending it with a newline as files do.
     *
     * @param PyStringNode $file The text of the grammar file
     */
    #[Given('the grammar file:')]
    public function theGrammarFile(PyStringNode $file): void
    {
        $this->source = $file->getRaw() . "\n";
        $this->tree = null;
        $this->failure = null;
    }

    /**
     * Takes the grammar file of the scenario exactly as written, without a final newline.
     *
     * @param PyStringNode $file The text of the grammar file
     */
    #[Given('the grammar file without a final newline:')]
    public function theGrammarFileWithoutAFinalNewline(PyStringNode $file): void
    {
        $this->source = $file->getRaw();
        $this->tree = null;
        $this->failure = null;
    }

    /**
     * Takes the names defined for the parse, as Lemon's -D options define them.
     *
     * @param string $names The names, separated by commas
     */
    #[Given('/^the names defined: (.+)$/')]
    public function theNamesDefined(string $names): void
    {
        $this->defines = array_map(trim(...), explode(',', $names));
    }

    /**
     * Runs the parser on the grammar file, keeping either the tree or the syntax error.
     */
    #[When('the file is parsed')]
    public function theFileIsParsed(): void
    {
        try {
            $this->tree = $this->parser->parse($this->source, $this->defines);
        } catch (SyntaxException $failure) {
            $this->failure = $failure;
        }
    }

    /**
     * States the whole tree the parse produced, one node per line.
     *
     * @param PyStringNode $expected The rendering of the tree, as TreeDumper writes it
     *
     * @throws AssertionFailedError When parsing failed or produced another tree
     */
    #[Then('the tree is:')]
    public function theTreeIs(PyStringNode $expected): void
    {
        Assert::assertSame($expected->getRaw(), $this->dumper->dump($this->tree()));
    }

    /**
     * States that the parse produced the same tree as another grammar file does, under the same names.
     *
     * @param PyStringNode $other The other grammar file, ended with a newline
     *
     * @throws AssertionFailedError When parsing failed or the trees differ
     */
    #[Then('the tree is the same as for:')]
    public function theTreeIsTheSameAsFor(PyStringNode $other): void
    {
        Assert::assertSame($this->dumper->dump($this->parser->parse($other->getRaw() . "\n", $this->defines)), $this->dumper->dump($this->tree()));
    }

    /**
     * States that parsing failed, and where.
     *
     * @param string $line The line of the error, counted from 1
     * @param string $column The column of the error, counted from 1
     *
     * @throws AssertionFailedError When parsing succeeded or failed elsewhere
     */
    #[Then('parsing fails at line :line column :column')]
    public function parsingFailsAt(string $line, string $column): void
    {
        if ($this->failure === null) {
            throw new AssertionFailedError("Parsing succeeded:\n" . $this->dumper->dump($this->tree()));
        }
        Assert::assertSame("{$line}:{$column}", (string) $this->failure->location, $this->failure->getMessage());
    }

    /**
     * States the message of the error, as Lemon words it.
     *
     * @param PyStringNode $message The message, without the position
     *
     * @throws AssertionFailedError When parsing succeeded or the message differs
     */
    #[Then('the error says:')]
    public function theErrorSays(PyStringNode $message): void
    {
        if ($this->failure === null) {
            throw new AssertionFailedError("Parsing succeeded:\n" . $this->dumper->dump($this->tree()));
        }
        Assert::assertSame($message->getRaw() . ' at ' . $this->failure->location, $this->failure->getMessage());
    }

    /**
     * The tree of the last parse.
     *
     * @return GrammarFile The tree
     *
     * @throws AssertionFailedError When there was no parse, or it failed
     */
    public function tree(): GrammarFile
    {
        if ($this->failure !== null) {
            throw new AssertionFailedError('Parsing failed: ' . $this->failure->getMessage());
        }
        if ($this->tree === null) {
            throw new AssertionFailedError('The file has not been parsed');
        }

        return $this->tree;
    }
}
