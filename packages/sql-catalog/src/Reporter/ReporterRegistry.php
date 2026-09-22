<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter;

/**
 * The reporters available to a run.
 *
 * @visibility root
 */
final class ReporterRegistry
{
    /**
     * @var array<string, ReporterInterface>
     */
    private array $reporters = [];

    /**
     * @param list<ReporterInterface> $reporters The reporters to register
     */
    public function __construct(array $reporters = [])
    {
        foreach ($reporters as $reporter) {
            $this->register($reporter);
        }
    }

    /**
     * The registry holding every reporter that ships with the package.
     */
    public static function withBuiltins(): self
    {
        return new self([new JsonReporter(), new HtmlReporter(), new TextReporter()]);
    }

    /**
     * Adds a reporter, replacing any registered under the same name.
     */
    public function register(ReporterInterface $reporter): void
    {
        $this->reporters[$reporter->name()] = $reporter;
    }

    /**
     * The registered names, in alphabetical order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->reporters);
        sort($names);

        return $names;
    }

    /**
     * Whether a reporter is registered under that name.
     */
    public function has(string $name): bool
    {
        return isset($this->reporters[$name]);
    }

    /**
     * The reporter registered under that name.
     *
     * @throws UnknownReporterException When nothing is registered under the name
     */
    public function get(string $name): ReporterInterface
    {
        return $this->reporters[$name] ?? throw new UnknownReporterException($name, $this->names());
    }

    /**
     * The registered reporters, in alphabetical order.
     *
     * @return list<ReporterInterface>
     */
    public function all(): array
    {
        $reporters = [];
        foreach ($this->names() as $name) {
            $reporters[] = $this->reporters[$name];
        }

        return $reporters;
    }
}
