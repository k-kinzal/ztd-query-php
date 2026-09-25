<?php

declare(strict_types=1);

namespace Requirements\Markdown;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Requirements\Config\Fields;
use Requirements\Config\SchemaValidator;
use stdClass;
use Symfony\Component\Yaml\Yaml;

/** Evaluates the bundled document-schema profile; it is not a general dialect implementation. */
final class DocumentSchema
{
    public function validate(Document $document, stdClass $frontmatter, string $file): void
    {
        $schema = Fields::mapping(Yaml::parseFile(SchemaValidator::path('definition.document.yaml')), 'document-schema');
        Fields::keys($schema, ['$schema', 'frontmatter', 'maxDepth', 'additionalSections', 'additionalBlocks', 'sections', 'allBlocks'], 'document-schema');
        $header = clone $frontmatter;
        unset($header->{'$schema'});
        $frontSchema = json_decode(json_encode($schema['frontmatter'], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        if (!$frontSchema instanceof stdClass) {
            throw new InvalidArgumentException('The document profile needs an object frontmatter schema.');
        }
        $error = (new Validator())->validate($header, $frontSchema)->error();
        if ($error !== null) {
            throw new InvalidArgumentException("$file: document-schema frontmatter: " . json_encode((new ErrorFormatter())->format($error), JSON_THROW_ON_ERROR));
        }
        $sections = Fields::sequence($schema['sections'], 'sections');
        if (count($sections) !== 1) {
            throw new InvalidArgumentException('The bundled Markdown profile must have one repeating item section.');
        }
        $section = Fields::mapping($sections[0], 'section');
        Fields::keys($section, ['header', 'minContains', 'maxContains', 'blocks', 'additionalBlocks', 'additionalSections'], 'section');
        $blocks = [];
        $count = 0;
        foreach ($document->children() as $node) {
            if ($node instanceof Heading) {
                if ($count > 0) {
                    $this->blocks($blocks, $section, $file);
                } elseif ($blocks !== [] && ($schema['additionalBlocks'] ?? true) === false) {
                    throw new InvalidArgumentException("$file: document-schema forbids content before the first heading.");
                }
                if ($node->getLevel() !== ($schema['maxDepth'] ?? 1) || !$this->matches(Nodes::text($node), Fields::mapping($section['header'], 'header'))) {
                    throw new InvalidArgumentException("$file: document-schema requires top-level item ID headings.");
                }
                ++$count;
                $blocks = [];
            } else {
                $blocks[] = $node;
                $this->allowed($node, Fields::mapping($schema['allBlocks'], 'allBlocks'), $file);
            }
        }
        $this->occurrences($count, $section, "$file: item sections");
        if ($count > 0) {
            $this->blocks($blocks, $section, $file);
        }
    }

    /** @param array<string, mixed> $schema */
    private function allowed(Node $node, array $schema, string $file): void
    {
        Fields::keys($schema, ['type'], 'allBlocks');
        if ($node instanceof HtmlBlock && $node->parent() instanceof BlockQuote && $node === $node->parent()->firstChild()) {
            Quotation::annotation($node);
            return;
        }
        if (!$node instanceof ListItem && !in_array($this->type($node), Fields::strings($schema['type'], 'allBlocks.type'), true)) {
            throw new InvalidArgumentException("$file: document-schema forbids " . $this->type($node) . ' blocks; use quotations, paragraphs and bullet lists.');
        }
        if ($node instanceof BlockQuote || $node instanceof ListBlock || $node instanceof ListItem) {
            foreach ($node->children() as $child) {
                $this->allowed($child, $schema, $file);
            }
        }
    }

    /**
     * @param list<Node> $nodes
     * @param array<string, mixed> $schema
     */
    private function blocks(array $nodes, array $schema, string $file): void
    {
        $entries = array_map(static fn (mixed $value): array => Fields::mapping($value, 'block'), Fields::sequence($schema['blocks'], 'blocks'));
        $counts = array_fill(0, count($entries), 0);
        $pointer = 0;
        foreach ($nodes as $node) {
            $found = false;
            for ($i = $pointer; $i < count($entries); ++$i) {
                $entry = $entries[$i];
                Fields::keys($entry, ['type', 'text', 'minContains', 'maxContains'], 'block');
                $types = is_string($entry['type']) ? [$entry['type']] : Fields::strings($entry['type'], 'block.type');
                if (in_array($this->type($node), $types, true) && (!isset($entry['text']) || $this->matches($node instanceof Paragraph ? Nodes::text($node) : '', Fields::mapping($entry['text'], 'block.text')))) {
                    $pointer = $i;
                    ++$counts[$i];
                    $found = true;
                    break;
                }
            }
            if (!$found && ($schema['additionalBlocks'] ?? true) === false) {
                throw new InvalidArgumentException("$file: unexpected document-schema block.");
            }
        }
        foreach ($entries as $i => $entry) {
            $this->occurrences($counts[$i], $entry, "$file: section block " . ($i + 1));
        }
    }

    /** @param array<string, mixed> $schema */
    private function occurrences(int $count, array $schema, string $context): void
    {
        $minimum = $schema['minContains'] ?? 1;
        $maximum = $schema['maxContains'] ?? PHP_INT_MAX;
        if (!is_int($minimum) || !is_int($maximum) || $count < $minimum || $count > $maximum) {
            throw new InvalidArgumentException("$context: document-schema occurrence constraint failed.");
        }
    }

    /** @param array<string, mixed> $schema */
    private function matches(string $text, array $schema): bool
    {
        Fields::keys($schema, ['pattern', 'const', 'enum'], 'text constraint');
        if (isset($schema['pattern']) && preg_match('~' . str_replace('~', '\\~', Fields::text($schema, 'pattern')) . '~u', $text) !== 1) {
            return false;
        }
        return (!isset($schema['const']) || $text === $schema['const']) && (!isset($schema['enum']) || in_array($text, Fields::strings($schema['enum'], 'enum'), true));
    }

    private function type(Node $node): string
    {
        return match (true) {
            $node instanceof Paragraph => 'paragraph',
            $node instanceof BlockQuote => 'quote',
            $node instanceof ListBlock => $node->getListData()->type === ListBlock::TYPE_BULLET ? 'bullet-list' : 'ordered-list',
            default => 'unsupported',
        };
    }
}
