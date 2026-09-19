<?php

declare(strict_types=1);

namespace SqlCatalog\Php;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Turns PHP source into a statement tree with fully qualified names.
 *
 * @visibility root
 */
final class SourceParser
{
    private Parser $parser;

    private NodeTraverser $traverser;

    /**
     * Builds a parser for the newest PHP version the installed library supports.
     */
    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser(new NameResolver(), new ParentConnectingVisitor());
    }

    /**
     * Parses source text that was read from the given path.
     *
     * Parse errors are collected rather than thrown, so the first one can name
     * the file it came from instead of arriving as a bare parser message.
     *
     * @param string $path The path reported in errors and recorded on the result
     * @param string $code The source text
     * @throws SyntaxException When the source is not valid PHP
     */
    public function parse(string $path, string $code): ParsedFile
    {
        $errors = new Collecting();
        $statements = $this->parser->parse($code, $errors);
        $collected = $errors->getErrors();

        if ($collected !== []) {
            throw new SyntaxException($path, $collected[0]->getRawMessage(), $collected[0]);
        }
        if ($statements === null) {
            throw new SyntaxException($path, 'the parser produced no statements');
        }

        return new ParsedFile($path, $this->resolveNames($statements));
    }

    /**
     * The statements with every name resolved and every parent link connected.
     *
     * @param array<array-key, Stmt> $statements
     * @return list<Stmt>
     */
    public function resolveNames(array $statements): array
    {
        $resolved = [];
        foreach ($this->traverser->traverse($statements) as $node) {
            if ($node instanceof Stmt) {
                $resolved[] = $node;
            }
        }

        return $resolved;
    }
}
