<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

/**
 * The extensions available to a run, and the calls they contribute.
 *
 * @visibility root
 */
final class ExtensionRegistry
{
    /**
     * @var array<string, ExtensionInterface>
     */
    private array $extensions = [];

    /**
     * @param list<ExtensionInterface> $extensions The extensions to register
     */
    public function __construct(array $extensions = [])
    {
        foreach ($extensions as $extension) {
            $this->register($extension);
        }
    }

    /**
     * The registry holding every extension that ships with the package.
     */
    public static function withBuiltins(): self
    {
        return new self([
            new PdoExtension(),
            new MysqliExtension(),
            new DoctrineExtension(),
            new LaravelExtension(),
            new WordPressExtension(),
        ]);
    }

    /**
     * Adds an extension, replacing any registered under the same name.
     */
    public function register(ExtensionInterface $extension): void
    {
        $this->extensions[$extension->name()] = $extension;
    }

    /**
     * The registered names, in alphabetical order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->extensions);
        sort($names);

        return $names;
    }

    /**
     * Whether an extension is registered under that name.
     */
    public function has(string $name): bool
    {
        return isset($this->extensions[$name]);
    }

    /**
     * The extension registered under that name.
     *
     * @throws UnknownExtensionException When nothing is registered under the name
     */
    public function get(string $name): ExtensionInterface
    {
        return $this->extensions[$name] ?? throw new UnknownExtensionException($name, $this->names());
    }

    /**
     * The extensions enabled by default when the command line names none.
     *
     * @return list<string>
     */
    public function defaultNames(): array
    {
        return array_values(array_filter(
            ['pdo', 'mysqli'],
            fn (string $name): bool => $this->has($name),
        ));
    }

    /**
     * The calls contributed by the named extensions, in the order they are named.
     *
     * @param list<string> $names
     * @return list<SinkSpec>
     * @throws UnknownExtensionException When one of the names is not registered
     */
    public function sinksOf(array $names): array
    {
        $sinks = [];
        foreach ($names as $name) {
            foreach ($this->get($name)->sinks() as $sink) {
                $sinks[] = $sink;
            }
        }

        return $sinks;
    }

    /**
     * The registered extensions, in alphabetical order.
     *
     * @return list<ExtensionInterface>
     */
    public function all(): array
    {
        $extensions = [];
        foreach ($this->names() as $name) {
            $extensions[] = $this->extensions[$name];
        }

        return $extensions;
    }
}
