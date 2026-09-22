<?php

declare(strict_types=1);

namespace Requirements\Source;

use DOMDocument;
use DOMElement;
use League\CommonMark\CommonMarkConverter;
use Requirements\Model\Source;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

final class DomSource implements SourceExtension
{
    public function __construct(private readonly ResourceLoader $loader = new ResourceLoader())
    {
    }

    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
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
        foreach ($crawler->filter($selector) as $node) {
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
        return $units;
    }
}
