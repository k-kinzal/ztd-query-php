<?php

declare(strict_types=1);

namespace Requirements\Source;

use DOMDocument;
use DOMElement;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;
use Override;
use Requirements\Model\Source;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Selects elements of HTML, XML, IETF XML and Markdown sources with CSS selectors.
 *
 * Markdown is rendered to HTML first. XML may not declare a DTD or entities. An evidence
 * selector may also be an exact Text Fragment within an HTML scope. A selection may not
 * contain both an element and one of its descendants.
 */
final class DomSource implements SourceExtension
{
    /**
     * @param ResourceLoader $loader Reads the source document
     */
    public function __construct(private readonly ResourceLoader $loader = new ResourceLoader())
    {
    }

    /**
     * Selects the elements a CSS selector or Text Fragment identifies.
     *
     * @param Source $source The source declaration
     * @param string $selector A CSS selector, or an exact Text Fragment within the HTML scope
     * @param string $directory The configuration directory
     * @param bool $live Whether to read the current URI instead of a pinned snapshot
     *
     * @return list<Unit> One unit per element, located by its node path
     *
     * @throws CommonMarkException When a Markdown source cannot be rendered
     * @throws RuntimeException When the source is unsafe or malformed, a fragment is misused or ancestors and descendants are selected together
     */
    #[Override]
    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        $text = TextFragment::isFragment($selector) ? TextFragment::text($selector) : null;
        if ($text !== null && ($source->format !== 'html' || TextFragment::isFragment($source->selector))) {
            throw new RuntimeException('Text Fragments select evidence in an HTML CSS scope; they cannot define the coverage scope.');
        }
        $content = $this->loader->read($source, $directory, $live);
        $crawler = new Crawler();
        if (in_array($source->format, ['xml', 'ietf'], true)) {
            if (stripos($content, '<!DOCTYPE') !== false || stripos($content, '<!ENTITY') !== false) {
                throw new RuntimeException('XML sources cannot contain DTD or entity declarations.');
            }
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $valid = $document->loadXML($content, LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (!$valid) {
                throw new RuntimeException('Malformed XML source.');
            }
            $crawler->addDocument($document);
        } else {
            if ($source->format === 'markdown') {
                $content = (string) (new CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]))->convert($content);
            }
            $crawler->addHtmlContent($content);
        }
        $units = [];
        foreach ($crawler->filter($text === null ? $selector : $source->selector) as $node) {
            if ($node instanceof DOMElement) {
                $location = $node->getNodePath();
                if ($location !== null) {
                    $units[] = new Unit($location, Unit::normalize($node->textContent));
                }
            }
        }
        foreach ($units as $unit) {
            foreach ($units as $other) {
                if ($unit !== $other && str_starts_with($other->location, $unit->location . '/')) {
                    throw new RuntimeException('A source selection cannot contain both ancestor and descendant units. Select atomic elements.');
                }
            }
        }
        return $text === null ? $units : array_values(array_filter($units, static fn (Unit $unit): bool => $unit->text === $text));
    }
}
