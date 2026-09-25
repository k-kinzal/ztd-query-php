<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use Requirements\Input\InvalidInputException;
use stdClass;

/**
 * Reads the item cards of a Markdown definition body.
 *
 * A card is an ATX "# ID" heading followed by badge rows, one statement paragraph, evidence
 * quotations and bold field sections, in that order.
 */
final class CardReader
{
    /**
     * @param Presentation $presentation Receives the badges, citations and links of each card
     * @param Citation|null $source Resolves citations against the definition's source
     */
    public function __construct(private readonly Presentation $presentation, private readonly ?Citation $source)
    {
    }

    /**
     * Reads every card of a document.
     *
     * @param Document $document The parsed body
     * @param string $body The body text, used to check the heading syntax
     * @param string $file The definition file
     *
     * @return list<stdClass> The items
     *
     * @throws InvalidInputException When a heading is not written as "# ID" or a card is malformed
     */
    public function cards(Document $document, string $body, string $file): array
    {
        $lines = explode("\n", $body);
        $items = [];
        $heading = null;
        $blocks = [];
        foreach ($document->children() as $node) {
            if ($node instanceof Heading) {
                if (preg_match('/^ {0,3}#[ \t]+/', $lines[($node->getStartLine() ?? 0) - 1] ?? '') !== 1) {
                    throw new InvalidInputException("$file: item headings must use ATX # ID syntax.");
                }
                if ($heading !== null) {
                    $items[] = $this->card($heading, $blocks, $file);
                }
                $heading = $node;
                $blocks = [];
            } else {
                $blocks[] = $node;
            }
        }
        if ($heading !== null) {
            $items[] = $this->card($heading, $blocks, $file);
        }
        return $items;
    }

    /**
     * Reads one card.
     *
     * @param Heading $heading The heading holding the item ID
     * @param list<Node> $blocks The blocks up to the next heading
     * @param string $file The definition file
     *
     * @return stdClass The item
     *
     * @throws InvalidInputException When the card lacks a statement or a badge, quotation or field is malformed
     */
    public function card(Heading $heading, array $blocks, string $file): stdClass
    {
        $id = Nodes::text($heading);
        $item = new stdClass();
        $item->id = $id;
        $badges = new Badges();
        while (isset($blocks[0]) && Badges::isParagraph($blocks[0])) {
            $badges->read($blocks[0], $item);
            array_shift($blocks);
        }
        $this->presentation->badges[$id] = $badges->images;
        $statement = array_shift($blocks);
        if (!$statement instanceof Paragraph || Nodes::field($statement) !== null) {
            throw new InvalidInputException("$file: $id needs a statement paragraph after its badges.");
        }
        $item->statement = preg_replace('/\s*\n\s*/', ' ', Nodes::text($statement, true));
        [$evidence, $blocks] = (new QuotationBlocks($this->presentation, $this->source))->read($blocks, $file, $id);
        if ($evidence !== []) {
            $item->evidence = $evidence;
        }
        (new FieldSections($this->presentation))->read($item, $blocks, $file, $id);
        return $item;
    }
}
