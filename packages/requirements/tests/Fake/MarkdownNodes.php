<?php

declare(strict_types=1);

namespace Tests\Fake;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use RuntimeException;

/**
 * Parses small Markdown texts into the CommonMark nodes that Markdown cards are read from.
 */
final class MarkdownNodes
{
    /**
     * Parses a Markdown text with the CommonMark core syntax.
     *
     * @param string $markdown The Markdown text
     *
     * @return Document The parsed document
     *
     * @throws CommonMarkException When the text cannot be parsed
     */
    public static function parse(string $markdown): Document
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        return (new MarkdownParser($environment))->parse($markdown);
    }

    /**
     * Returns the top-level blocks of a Markdown text.
     *
     * @param string $markdown The Markdown text
     *
     * @return list<Node> The blocks in document order
     *
     * @throws CommonMarkException When the text cannot be parsed
     */
    public static function blocks(string $markdown): array
    {
        $blocks = [];
        foreach (self::parse($markdown)->children() as $node) {
            $blocks[] = $node;
        }
        return $blocks;
    }

    /**
     * Returns the first top-level block of a Markdown text.
     *
     * @param string $markdown The Markdown text
     *
     * @return Node The first block
     *
     * @throws CommonMarkException When the text cannot be parsed
     * @throws RuntimeException When the text has no block
     */
    public static function first(string $markdown): Node
    {
        $node = self::parse($markdown)->firstChild();
        if ($node === null) {
            throw new RuntimeException('The Markdown text has no block.');
        }
        return $node;
    }
}
