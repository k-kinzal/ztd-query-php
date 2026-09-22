<?php

declare(strict_types=1);

namespace Requirements\Config;

use InvalidArgumentException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use Requirements\Markdown\DocumentSchema;
use Requirements\Markdown\Fields as MarkdownFields;
use Requirements\Markdown\Nodes;
use Requirements\Markdown\Reference;
use Requirements\Markdown\Writer;
use stdClass;
use Symfony\Component\Yaml\Yaml;

final class MarkdownDocument
{
    /** @var array<string, array<string, array<string, string>>> */
    private array $links = [];

    /** @var array<string, array<string, string>> */
    private array $badges = [];

    /** @var list<Reference> */
    private array $references = [];

    /** @param array<string, mixed> $options */
    public function read(string $file, array $options): stdClass
    {
        if (($options['experimental'] ?? false) !== true) {
            throw new InvalidArgumentException("$file: Markdown definitions require markdown.experimental: true.");
        }
        Fields::keys($options, ['experimental'], 'markdown');
        $this->links = [];
        $this->badges = [];
        $this->references = [];
        $text = file_get_contents($file);
        if ($text === false || preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/s', $text, $parts) !== 1) {
            throw new InvalidArgumentException("$file: expected YAML frontmatter delimited by ---.");
        }
        $data = Yaml::parse($parts[1], Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (!$data instanceof stdClass) {
            throw new InvalidArgumentException("$file: frontmatter must be a mapping.");
        }
        Fields::keys(Fields::mapping(get_object_vars($data), 'frontmatter'), ['$schema', 'version', 'source'], "$file frontmatter");
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
                    throw new InvalidArgumentException("$file: item headings must use ATX # ID syntax.");
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
        return (new Writer())->render($data, $this->links, $this->badges);
    }

    /** @param list<Node> $blocks */
    private function item(Heading $heading, array $blocks, string $file): stdClass
    {
        $id = Nodes::text($heading);
        $statement = array_shift($blocks);
        if (!$statement instanceof Paragraph || Nodes::field($statement) !== null) {
            throw new InvalidArgumentException("$file: $id needs a statement paragraph before its fields.");
        }
        $item = new stdClass();
        $item->id = $id;
        $item->statement = preg_replace('/\s*\n\s*/', ' ', Nodes::text($statement, true));
        $name = null;
        $values = [];
        foreach ($blocks as $block) {
            $field = Nodes::field($block);
            if ($field !== null) {
                if ($name !== null) {
                    $this->field($item, $name, $values, $file, $id);
                }
                $name = $field;
                $values = [];
            } elseif ($name === null) {
                throw new InvalidArgumentException("$file: $id expects a bold field heading after its statement.");
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
            throw new InvalidArgumentException("$file: $id has duplicate field '$name'.");
        }
        $reader = new MarkdownFields();
        try {
            $item->{$name} = $reader->read($name, $nodes);
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException("$file: $id.$name: " . $error->getMessage(), 0, $error);
        }
        $this->links[$id][$name] = $reader->links;
        foreach ($reader->links as $target => $url) {
            $this->references[] = new Reference($target, $url, $file);
        }
        if ($name === 'labels') {
            $this->badges[$id] = $reader->badges;
        }
    }
}
