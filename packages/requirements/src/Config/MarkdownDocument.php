<?php

declare(strict_types=1);

namespace Requirements\Config;

use JsonException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Parser\MarkdownParser;
use Requirements\Config\Markdown\CardReader;
use Requirements\Config\Markdown\Citation;
use Requirements\Config\Markdown\Frontmatter;
use Requirements\Config\Markdown\Presentation;
use Requirements\Config\Markdown\Profile\DocumentSchema;
use Requirements\Config\Markdown\Reference;
use Requirements\Config\Markdown\Render\CardWriter;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Reads a definition written as Markdown cards and writes it back in the same form.
 *
 * The YAML frontmatter holds the version and source; each top-level heading is an item ID
 * followed by badges, a statement, quoted evidence and bold field sections. What the author
 * chose beyond the data model, such as badge images and link targets, is remembered so that
 * formatting reproduces it.
 */
final class MarkdownDocument
{
    private Presentation $presentation;

    private ?Citation $source = null;

    /**
     * Starts with nothing remembered; each read replaces what the previous one remembered.
     */
    public function __construct()
    {
        $this->presentation = new Presentation();
    }

    /**
     * Reads a Markdown definition into the same shape as a YAML definition.
     *
     * @param string $file The definition file
     * @param array<string, mixed> $options The markdown options; experimental must be true
     * @param string|null $directory The configuration directory that the source URI resolves against
     *
     * @return stdClass The definition with its items
     *
     * @throws InvalidInputException When Markdown is not enabled or the document breaks the card syntax
     * @throws JsonException When the source cannot be converted
     * @throws ParseException When the frontmatter is malformed YAML
     */
    public function read(string $file, array $options, ?string $directory = null): stdClass
    {
        if (($options['experimental'] ?? false) !== true) {
            throw new InvalidInputException("$file: Markdown definitions require markdown.experimental: true.");
        }
        Fields::keys($options, ['experimental'], 'markdown');
        $this->presentation = new Presentation();
        $this->source = null;
        [$data, $body] = (new Frontmatter())->read($file);
        if (isset($data->source)) {
            $source = Fields::mapping(json_decode(json_encode($data->source, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR), 'source');
            $this->source = new Citation(Source::from($source), $file, $directory ?? dirname($file));
        }
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        try {
            $document = (new MarkdownParser($environment))->parse($body);
        } catch (CommonMarkException $error) {
            throw new InvalidInputException($error->getMessage(), 0, $error);
        }
        (new DocumentSchema())->validate($document, $data, $file);
        $data->items = (new CardReader($this->presentation, $this->source))->cards($document, $body, $file);
        return $data;
    }

    /**
     * Returns the links to other items found by the last read.
     *
     * @return list<Reference> The links, checked once every definition is loaded
     */
    public function references(): array
    {
        return $this->presentation->references;
    }

    /**
     * Writes a definition as Markdown cards, reusing what the last read remembered.
     *
     * @param stdClass $data The definition
     *
     * @return string The Markdown text
     *
     * @throws InvalidInputException When a record cannot be written as a card
     * @throws JsonException When a metadata value cannot be encoded
     */
    public function render(stdClass $data): string
    {
        return (new CardWriter())->render($data, $this->presentation->links, $this->presentation->badges, $this->presentation->citations, $this->source);
    }
}
