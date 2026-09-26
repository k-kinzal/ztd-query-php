<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use League\CommonMark\Node\Node;
use Requirements\Input\InvalidInputException;
use stdClass;

/**
 * Reads the bold field sections that end a card, such as **tests** or **reason**.
 *
 * "unsupport reason", "unsupported reason" and "rationale" are spellings of the reason field.
 */
final class FieldSections
{
    /**
     * @param Presentation $presentation Receives the link targets, label badges and references of each field
     */
    public function __construct(private readonly Presentation $presentation)
    {
    }

    /**
     * Reads every field section of a card.
     *
     * @param stdClass $item The item being read
     * @param list<Node> $blocks The blocks after the evidence quotations
     * @param string $file The definition file
     * @param string $id The item ID
     *
     * @throws InvalidInputException When content precedes the first field heading or a field is malformed or repeated
     */
    public function read(stdClass $item, array $blocks, string $file, string $id): void
    {
        $name = null;
        $values = [];
        foreach ($blocks as $block) {
            $field = Nodes::field($block);
            if ($field !== null) {
                if ($name !== null) {
                    $this->assign($item, $name, $values, $file, $id);
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
            $this->assign($item, $name, $values, $file, $id);
        }
    }

    /**
     * Reads one field section into the item.
     *
     * @param stdClass $item The item being read
     * @param string $name The field name
     * @param list<Node> $nodes The blocks of the section
     * @param string $file The definition file
     * @param string $id The item ID
     *
     * @throws InvalidInputException When the field repeats or its value is malformed
     */
    public function assign(stdClass $item, string $name, array $nodes, string $file, string $id): void
    {
        if (property_exists($item, $name)) {
            throw new InvalidInputException("$file: $id has duplicate field '$name'.");
        }
        $reader = new FieldReader();
        try {
            $item->{$name} = $reader->read($name, $nodes);
        } catch (InvalidInputException $error) {
            throw new InvalidInputException("$file: $id.$name: " . $error->getMessage(), 0, $error);
        }
        $this->presentation->links[$id][$name] = $reader->links;
        foreach ($reader->links as $target => $url) {
            $this->presentation->references[] = new Reference($target, $url, $file);
        }
        if ($name === 'labels') {
            foreach ($reader->badges as $value => $url) {
                $this->presentation->badges[$id]['label'][$value] = ['url' => $url, 'title' => null];
            }
        }
    }
}
