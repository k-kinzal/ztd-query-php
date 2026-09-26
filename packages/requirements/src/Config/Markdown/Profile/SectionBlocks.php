<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Profile;

use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use Requirements\Config\Markdown\Nodes;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * Checks the blocks of one item section against the ordered block entries of the profile.
 *
 * Each block matches the first entry, at or after the previous match, whose kinds and text
 * constraint it satisfies; every entry must then occur as often as it allows.
 */
final class SectionBlocks
{
    /**
     * Checks the blocks of a section.
     *
     * @param list<Node> $nodes The blocks under one heading
     * @param array<string, mixed> $schema The profile's section
     * @param string $file The definition file
     *
     * @throws InvalidInputException When a block matches no entry where none is allowed, or an entry occurs too often or too rarely
     */
    public static function check(array $nodes, array $schema, string $file): void
    {
        $entries = [];
        foreach (Fields::sequence($schema['blocks'], 'blocks') as $value) {
            $entries[] = Fields::mapping($value, 'block');
        }
        $counts = array_fill(0, count($entries), 0);
        $pointer = 0;
        foreach ($nodes as $node) {
            $found = false;
            for ($i = $pointer; $i < count($entries); ++$i) {
                $entry = $entries[$i];
                Fields::keys($entry, ['type', 'text', 'minContains', 'maxContains'], 'block');
                $types = is_string($entry['type']) ? [$entry['type']] : Fields::strings($entry['type'], 'block.type');
                if (in_array(BlockKind::of($node), $types, true) && (!isset($entry['text']) || TextConstraint::matches($node instanceof Paragraph ? Nodes::text($node) : '', Fields::mapping($entry['text'], 'block.text')))) {
                    $pointer = $i;
                    ++$counts[$i];
                    $found = true;
                    break;
                }
            }
            if (!$found && ($schema['additionalBlocks'] ?? true) === false) {
                throw new InvalidInputException("$file: unexpected document-schema block.");
            }
        }
        foreach ($entries as $i => $entry) {
            Occurrences::check($counts[$i], $entry, "$file: section block " . ($i + 1));
        }
    }
}
