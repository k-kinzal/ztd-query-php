<?php

declare(strict_types=1);

namespace Requirements\Config;

use InvalidArgumentException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Parser\MarkdownParser;
use stdClass;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

final class MarkdownDocument
{
    /** @param array<string, mixed> $options */
    public function read(string $file, array $options): stdClass
    {
        if (($options['experimental'] ?? false) !== true) {
            throw new InvalidArgumentException("$file: Markdown definitions require markdown.experimental: true.");
        }
        $command = Fields::strings($options['command'] ?? ['schematter'], 'markdown.command', false);
        $timeout = $options['timeout'] ?? 30;
        if ($command === [] || (!is_int($timeout) && !is_float($timeout)) || $timeout <= 0) {
            throw new InvalidArgumentException('Markdown requires a nonempty schematter command and positive timeout.');
        }
        $process = new Process([...$command, 'validate', $file, '--schema', SchemaValidator::path('definition.document.yaml'), '--format', 'json'], timeout: (float) $timeout);
        if ($process->run() !== 0) {
            throw new InvalidArgumentException("$file: document-schema validation failed (install schematter 0.2.0): " . $process->getOutput() . $process->getErrorOutput());
        }
        $text = file_get_contents($file);
        if ($text === false || preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/s', $text, $parts) !== 1) {
            throw new InvalidArgumentException("$file: expected YAML frontmatter delimited by ---.");
        }
        $data = Yaml::parse($parts[1], Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (!$data instanceof stdClass) {
            throw new InvalidArgumentException("$file: frontmatter must be a mapping.");
        }
        foreach (array_keys(get_object_vars($data)) as $key) {
            if (!in_array($key, ['$schema', 'version', 'source'], true)) {
                throw new InvalidArgumentException("$file: unknown frontmatter field '$key'.");
            }
        }
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $document = (new MarkdownParser($environment))->parse($parts[2]);
        $lines = explode("\n", str_replace("\r\n", "\n", $parts[2]));
        $items = [];
        $item = null;
        foreach ($document->children() as $node) {
            if ($node instanceof Heading) {
                $line = $lines[($node->getStartLine() ?? 1) - 1];
                if ($node->getLevel() !== 1 || preg_match('/^# ([A-Za-z][A-Za-z0-9_.-]*)\s*$/D', $line, $match) !== 1) {
                    throw new InvalidArgumentException("$file: item headings must use # ID.");
                }
                $item = new stdClass();
                $item->id = $match[1];
                $items[] = $item;
            } elseif ($item !== null && $node instanceof Paragraph && !isset($item->statement)) {
                $start = $node->getStartLine() ?? 1;
                $end = $node->getEndLine() ?? $start;
                $item->statement = implode(' ', array_map(trim(...), array_slice($lines, $start - 1, $end - $start + 1)));
            } elseif ($item !== null && isset($item->statement) && $node instanceof FencedCode && $node->getInfo() === 'yaml') {
                $attributes = Yaml::parse($node->getLiteral(), Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
                if (!$attributes instanceof stdClass) {
                    throw new InvalidArgumentException("$file: the yaml fence must contain item fields.");
                }
                foreach (get_object_vars($attributes) as $key => $value) {
                    if (property_exists($item, $key)) {
                        throw new InvalidArgumentException("$file: duplicate item field '$key'.");
                    }
                    $item->{$key} = $value;
                }
            } else {
                throw new InvalidArgumentException("$file: expected an item heading, statement paragraph, then optional yaml fence.");
            }
        }
        $data->items = $items;
        return $data;
    }

    public function render(stdClass $data): string
    {
        $header = clone $data;
        unset($header->items);
        $text = "---\n" . DocumentReader::yaml($header) . "---\n";
        foreach (Fields::sequence($data->items, 'items') as $entry) {
            if (!$entry instanceof stdClass || !is_string($entry->id) || !is_string($entry->statement)) {
                throw new InvalidArgumentException('Cannot format invalid Markdown item.');
            }
            $text .= "\n# $entry->id\n\n$entry->statement\n";
            $attributes = clone $entry;
            unset($attributes->id, $attributes->statement);
            if (get_object_vars($attributes) !== []) {
                $yaml = DocumentReader::yaml($attributes);
                $fence = '```';
                while (str_contains($yaml, $fence)) {
                    $fence .= '`';
                }
                $text .= "\n{$fence}yaml\n$yaml$fence\n";
            }
        }
        return $text;
    }
}
