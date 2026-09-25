<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Profile;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Node\Node;
use Requirements\Config\Markdown\Quotation;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * Checks that a block and its nested blocks are of the kinds the profile allows anywhere.
 *
 * A selector comment is allowed only as the first block of a quotation.
 */
final class AllowedBlocks
{
    /**
     * Checks a block and, for quotations and lists, every block inside it.
     *
     * @param Node $node The block
     * @param array<string, mixed> $schema The profile's allBlocks constraint
     * @param string $file The definition file
     *
     * @throws InvalidInputException When a block kind is not allowed or a comment is misplaced
     */
    public static function check(Node $node, array $schema, string $file): void
    {
        Fields::keys($schema, ['type'], 'allBlocks');
        if ($node instanceof HtmlBlock && $node->parent() instanceof BlockQuote && $node === $node->parent()->firstChild()) {
            Quotation::annotation($node);
            return;
        }
        if (!$node instanceof ListItem && !in_array(BlockKind::of($node), Fields::strings($schema['type'], 'allBlocks.type'), true)) {
            throw new InvalidInputException("$file: document-schema forbids " . BlockKind::of($node) . ' blocks; use quotations, paragraphs and bullet lists.');
        }
        if ($node instanceof BlockQuote || $node instanceof ListBlock || $node instanceof ListItem) {
            foreach ($node->children() as $child) {
                self::check($child, $schema, $file);
            }
        }
    }
}
