<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Profile;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;

/**
 * Names block kinds the way the profile does.
 */
final class BlockKind
{
    /**
     * Names the kind of a block.
     *
     * @param Node $node The block
     *
     * @return string paragraph, quote, bullet-list, ordered-list or unsupported
     */
    public static function of(Node $node): string
    {
        return match (true) {
            $node instanceof Paragraph => 'paragraph',
            $node instanceof BlockQuote => 'quote',
            $node instanceof ListBlock => $node->getListData()->type === ListBlock::TYPE_BULLET ? 'bullet-list' : 'ordered-list',
            default => 'unsupported',
        };
    }
}
