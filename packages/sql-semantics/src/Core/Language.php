<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use InvalidArgumentException;
use LogicException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Analysis\Vocabulary;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Comments;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\StatementException;
use SqlSemantics\Statement\Syntax;
use WeakMap;

/**
 * The language SQL is read and written in: a dialect, a grammar release, a mode, and a parameter syntax.
 *
 * Every entry point resolves its language once, so the parser, the statement
 * vocabulary of the release, and the settings the text is read under are the
 * same for analysis, splitting, and composition. The language is also the
 * syntax of every statement it reads or composes: it verifies that the SQL a
 * statement writes is read back, by this release under this mode, as the
 * same command with the same comments.
 *
 * @visibility public
 * @example Resolving the default release of a dialect
 *     $resolve = static fn (\SqlSemantics\Core\Dialect $dialect): string => (new \SqlSemantics\Core\Language($dialect))->version;
 *     $resolve instanceof \Closure // => true
 */
final class Language implements Syntax
{
    /**
     * The resolved grammar release tag.
     */
    public readonly string $version;

    private readonly SqlParser $parser;

    private ?ValueReader $values = null;

    /**
     * The comments each command was verified with; values are immutable, so a verification holds for good.
     *
     * @var WeakMap<Command, list<Comments>>
     */
    private WeakMap $verified;

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
        $this->verified = new WeakMap();
    }

    /**
     * Keeps what identifies the language, so a serialized statement holds no parser or cache.
     *
     * @return array{dialect: Dialect, version: string, mode: Mode|null, parameters: Parameters}
     */
    public function __serialize(): array
    {
        return ['dialect' => $this->dialect, 'version' => $this->version, 'mode' => $this->mode, 'parameters' => $this->parameters];
    }

    /**
     * Resolves the language again from what identifies it.
     *
     * @param array{dialect: Dialect, version: string, mode: Mode|null, parameters: Parameters} $data
     */
    public function __unserialize(array $data): void
    {
        $this->dialect = $data['dialect'];
        $this->mode = $data['mode'];
        $this->parameters = $data['parameters'];
        $this->parser = $this->dialect->platform()->parser($data['version'], $this->mode, $this->parameters);
        $this->version = $this->parser->version();
        $this->verified = new WeakMap();
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

    /**
     * Requires the SQL the statement writes to be read back by this language as the same command and comments.
     *
     * @throws StatementException When the SQL does not parse in this language, or parses as other SQL
     * @throws LogicException When parser and model resources disagree
     */
    public function verify(Statement $statement): void
    {
        $verified = $this->verified[$statement->command] ?? [];
        foreach ($verified as $comments) {
            if ($comments->equals($statement->comments)) {
                return;
            }
        }
        (new Verification\Readback($this))->check($statement);
        $verified[] = $statement->comments;
        $this->verified[$statement->command] = $verified;
    }
}
