<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use Requirements\Input\InvalidInputException;
use stdClass;

/**
 * Reads the evidence quotations that follow a card's statement.
 *
 * Each block quotation may be followed by a paragraph holding only its source link.
 */
final class QuotationBlocks
{
    /**
     * @param Presentation $presentation Receives the citation link of each quotation
     * @param Citation|null $source Resolves citations against the definition's source
     */
    public function __construct(private readonly Presentation $presentation, private readonly ?Citation $source)
    {
    }

    /**
     * Reads the leading quotations of the remaining card blocks.
     *
     * @param list<Node> $blocks The blocks after the statement
     * @param string $file The definition file
     * @param string $id The item ID
     *
     * @return array{list<stdClass>, list<Node>} The evidence entries and the blocks after them
     *
     * @throws InvalidInputException When a quotation lacks a source, a selector or text, or its citation disagrees
     */
    public function read(array $blocks, string $file, string $id): array
    {
        $evidence = [];
        while (isset($blocks[0]) && $blocks[0] instanceof BlockQuote) {
            $quote = $blocks[0];
            array_shift($blocks);
            $attribution = null;
            if (isset($blocks[0]) && $blocks[0] instanceof Paragraph && $blocks[0]->firstChild() instanceof Link && $blocks[0]->firstChild()->next() === null) {
                $attribution = Nodes::link($blocks[0]);
                array_shift($blocks);
            }
            $reader = new Quotation();
            try {
                $evidence[] = $reader->read($quote, $this->source, $attribution);
            } catch (InvalidInputException $error) {
                throw new InvalidInputException("$file: $id: " . $error->getMessage(), 0, $error);
            }
            $this->presentation->citations[$id][] = $reader->link;
        }
        return [$evidence, $blocks];
    }
}
