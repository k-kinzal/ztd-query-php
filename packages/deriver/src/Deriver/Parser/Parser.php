<?php

declare(strict_types=1);

namespace Deriver\Deriver\Parser;

use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Sources\Source;
use Deriver\Exception\ParserException;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser as PhpParser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

/**
 * Parses one captured source into a syntax root without executing its contents.
 * @visibility root
 * @example Parsing a source with an explicit PHP version
 *     $source = new \Deriver\Deriver\Sources\Source('app.php', '<?php return 1;');
 *     $parser = new \Deriver\Deriver\Parser\Parser(new \Deriver\Deriver\Parser\ParserConfig('8.3'));
 *     $node = $parser->parse($source);
 *     $node->getType() // => 'File'
 */
final class Parser
{
    private readonly PhpParser $parser;

    /**
     * Creates one PHP parser for the configured language version.
     */
    public function __construct(private readonly ParserConfig $config)
    {
        $this->parser = (new ParserFactory())->createForVersion(PhpVersion::fromString($config->phpVersion));
    }

    /**
     * Builds an unresolved syntax tree for the configured PHP language version.
     * @return File Root node implementing the same Node interface as every descendant
     * @throws ParserException If parsing or language-version syntax validation fails
     */
    public function parse(Source $source): Node
    {
        $errors = new Collecting();
        $parsed = $this->parser->parse($source->contents, $errors) ?? [];
        if (!$errors->hasErrors()) {
            (new NodeTraverser(new TargetSyntax($this->config, $errors, array_values($this->parser->getTokens()))))->traverse($parsed);
        }

        $error = $errors->getErrors()[0] ?? null;
        if ($error !== null) {
            throw new ParserException($error->getRawMessage(), $source->path, max(1, $error->getStartLine()), $error);
        }
        return new File($source, array_values($parsed));
    }
}
