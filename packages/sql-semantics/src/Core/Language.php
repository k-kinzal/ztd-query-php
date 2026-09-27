<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use InvalidArgumentException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Analysis\Vocabulary;

/**
 * The language SQL is read and written in: a dialect, a grammar release, a mode, and a parameter syntax.
 *
 * Every entry point resolves its language once, so the parser, the statement
 * vocabulary of the release, and the settings the text is read under are the
 * same for analysis, splitting, and composition.
 *
 * @visibility public
 * @example Resolving the default release of a dialect
 *     $resolve = static fn (\SqlSemantics\Core\Dialect $dialect): string => (new \SqlSemantics\Core\Language($dialect))->version;
 *     $resolve instanceof \Closure // => true
 */
final class Language
{
    /**
     * The resolved grammar release tag.
     */
    public readonly string $version;

    private readonly SqlParser $parser;

    private ?ValueReader $values = null;

    /**
     * @param Dialect $dialect The database
     * @param string|null $grammarVersion A release tag the dialect ships, or null for its default
     * @param Mode|null $mode The session settings text is read under, or null for the server's defaults
     * @param Parameters $parameters Which parameter markers are read
     *
     * @throws InvalidArgumentException When the mode does not belong to the dialect
     */
    public function __construct(
        public readonly Dialect $dialect,
        ?string $grammarVersion = null,
        public readonly ?Mode $mode = null,
        public readonly Parameters $parameters = Parameters::Native,
    ) {
        $this->parser = $dialect->platform()->parser($grammarVersion, $mode, $parameters);
        $this->version = $this->parser->version();
    }

    /**
     * Answers the parser of the release, configured with the mode and parameter syntax.
     */
    public function parser(): SqlParser
    {
        return $this->parser;
    }

    /**
     * Answers the reader that lowers parse trees of the release into statement values.
     */
    public function values(): ValueReader
    {
        return $this->values ??= $this->dialect->platform()->values($this->version);
    }

    /**
     * Answers the statement vocabulary of the release.
     */
    public function vocabulary(): Vocabulary
    {
        return $this->values()->vocabulary;
    }
}
