<?php

declare(strict_types=1);

namespace Requirements\Config;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use Requirements\Config\Markdown\Badges;
use Requirements\Config\Markdown\Citation;
use Requirements\Config\Markdown\DocumentSchema;
use Requirements\Config\Markdown\Fields as MarkdownFields;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Quotation;
use Requirements\Config\Markdown\Reference;
use Requirements\Config\Markdown\Writer;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use stdClass;
use Symfony\Component\Yaml\Yaml;

final class MarkdownDocument
{
    /** @var array<string, array<string, array<string, string>>> */
    private array $links = [];

    /** @var array<string, array<string, array<string, array{url: string, title: ?string}>>> */
    private array $badges = [];

    /** @var array<string, list<array{url: string, label: string}|null>> */
    private array $citations = [];

    private ?Citation $source = null;

    /** @var list<Reference> */
    private array $references = [];

    /** @param array<string, mixed> $options */
    public function read(string $file, array $options, ?string $directory = null): stdClass
    {
        if (($options['experimental'] ?? false) !== true) {
            throw new InvalidInputException("$file: Markdown definitions require markdown.experimental: true.");
        }
        Fields::keys($options, ['experimental'], 'markdown');
        $this->links = [];
        $this->badges = [];
        $this->references = [];
        $this->citations = [];
        $this->source = null;
        $text = file_get_contents($file);
        if ($text === false || preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/s', $text, $parts) !== 1) {
            throw new InvalidInputException("$file: expected YAML frontmatter delimited by ---.");
        }
        $data = Yaml::parse($parts[1], Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (!$data instanceof stdClass) {
            throw new InvalidInputException("$file: frontmatter must be a mapping.");
        }
        Fields::keys(Fields::mapping(get_object_vars($data), 'frontmatter'), ['$schema', 'version', 'source'], "$file frontmatter");
        if (isset($data->source)) {
            $source = Fields::mapping(json_decode(json_encode($data->source, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR), 'source');
            $this->source = new Citation(Source::from($source), $file, $directory ?? dirname($file));
        }
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $document = (new MarkdownParser($environment))->parse($parts[2]);
        (new DocumentSchema())->validate($document, $data, $file);
        $lines = explode("\n", $parts[2]);
        $items = [];
        $heading = null;
        $blocks = [];
        foreach ($document->children() as $node) {
            if ($node instanceof Heading) {
                if (preg_match('/^ {0,3}#[ \t]+/', $lines[($node->getStartLine() ?? 0) - 1] ?? '') !== 1) {
                    throw new InvalidInputException("$file: item headings must use ATX # ID syntax.");
                }
                if ($heading !== null) {
                    $items[] = $this->item($heading, $blocks, $file);
                }
                $heading = $node;
                $blocks = [];
            } else {
                $blocks[] = $node;
            }
        }
        if ($heading !== null) {
            $items[] = $this->item($heading, $blocks, $file);
        }
        $data->items = $items;
        return $data;
    }

    /** @return list<Reference> */
    public function references(): array
    {
        return $this->references;
    }

    public function render(stdClass $data): string
    {
        return (new Writer())->render($data, $this->links, $this->badges, $this->citations, $this->source);
    }

    /** @param list<Node> $blocks */
    private function item(Heading $heading, array $blocks, string $file): stdClass
    {
        $id = Nodes::text($heading);
        $item = new stdClass();
        $item->id = $id;
        $badges = new Badges();
        while (isset($blocks[0]) && Badges::isParagraph($blocks[0])) {
            $badge = array_shift($blocks);
            $badges->read($badge, $item);
        }
        $this->badges[$id] = $badges->images;
        $statement = array_shift($blocks);
        if (!$statement instanceof Paragraph || Nodes::field($statement) !== null) {
            throw new InvalidInputException("$file: $id needs a statement paragraph after its badges.");
        }
        $item->statement = preg_replace('/\s*\n\s*/', ' ', Nodes::text($statement, true));
        $evidence = [];
        while (isset($blocks[0]) && $blocks[0] instanceof BlockQuote) {
            $quote = $blocks[0];
            array_shift($blocks);
            $attribution = null;
            if (isset($blocks[0]) && $blocks[0] instanceof Paragraph && $blocks[0]->firstChild() instanceof Link && $blocks[0]->firstChild()->next() === null) {
                $attribution = Nodes::link(array_shift($blocks));
            }
            $reader = new Quotation();
            try {
                $evidence[] = $reader->read($quote, $this->source, $attribution);
            } catch (InvalidInputException $error) {
                throw new InvalidInputException("$file: $id: " . $error->getMessage(), 0, $error);
            }
            $this->citations[$id][] = $reader->link;
        }
        if ($evidence !== []) {
            $item->evidence = $evidence;
        }
        $name = null;
        $values = [];
        foreach ($blocks as $block) {
            $field = Nodes::field($block);
            if ($field !== null) {
                if ($name !== null) {
                    $this->field($item, $name, $values, $file, $id);
                }
                $name = match ($field) {
                    'unsupport reason', 'unsupported reason', 'rationale' => 'reason',
                    default => $field,
                };
                $values = [];
            } elseif ($name === null) {
                throw new InvalidInputException("$file: $id expects a bold field heading after its statement.");
            } else {
                $values[] = $block;
            }
        }
        if ($name !== null) {
            $this->field($item, $name, $values, $file, $id);
        }
        return $item;
    }

    /** @param list<Node> $nodes */
    private function field(stdClass $item, string $name, array $nodes, string $file, string $id): void
    {
        if (property_exists($item, $name)) {
            throw new InvalidInputException("$file: $id has duplicate field '$name'.");
        }
        $reader = new MarkdownFields();
        try {
            $item->{$name} = $reader->read($name, $nodes);
        } catch (InvalidInputException $error) {
            throw new InvalidInputException("$file: $id.$name: " . $error->getMessage(), 0, $error);
        }
        $this->links[$id][$name] = $reader->links;
        foreach ($reader->links as $target => $url) {
            $this->references[] = new Reference($target, $url, $file);
        }
        if ($name === 'labels') {
            foreach ($reader->badges as $value => $url) {
                $this->badges[$id]['label'][$value] = ['url' => $url, 'title' => null];
            }
        }
    }
}
