<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Input\InvalidInputException;

final class Registry
{
    /** @var array<string, SourceExtension> */
    private array $extensions;

    /** @param array<string, string> $classes */
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

    public function get(string $name): SourceExtension
    {
        return $this->extensions[$name] ?? throw new InvalidInputException("Unknown source extension: $name");
    }
}
