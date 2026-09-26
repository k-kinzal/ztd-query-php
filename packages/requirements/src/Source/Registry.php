<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Input\InvalidInputException;

/**
 * The source extensions available to a project by format.
 *
 * html, xml, ietf and markdown are read as documents, json with JSONPath and text by line;
 * configured extension classes are added under their format names and may replace these.
 */
final class Registry
{
    /**
     * @var array<string, SourceExtension>
     */
    private array $extensions;

    /**
     * @param array<string, string> $classes Extension class names by format
     *
     * @throws InvalidInputException When a class does not implement SourceExtension
     */
    public function __construct(array $classes = [])
    {
        $loader = new ResourceLoader();
        $dom = new DomSource($loader);
        $this->extensions = ['html' => $dom, 'xml' => $dom, 'ietf' => $dom, 'markdown' => $dom, 'json' => new JsonSource($loader), 'text' => new TextSource($loader)];
        foreach ($classes as $name => $class) {
            if (!is_a($class, SourceExtension::class, true)) {
                throw new InvalidInputException("$class must implement SourceExtension.");
            }
            $this->extensions[$name] = new $class();
        }
    }

    /**
     * Returns the extension registered for a format.
     *
     * @param string $name The format
     *
     * @return SourceExtension The extension
     *
     * @throws InvalidInputException When no extension reads that format
     */
    public function get(string $name): SourceExtension
    {
        return $this->extensions[$name] ?? throw new InvalidInputException("Unknown source extension: $name");
    }
}
