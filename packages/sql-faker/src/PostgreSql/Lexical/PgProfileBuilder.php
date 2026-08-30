<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Lexical;

use RuntimeException;
use SqlFaker\Grammar\Source\LexerSource;
use SqlFaker\Grammar\Source\UpstreamLexerSource;
use SqlFaker\PostgreSql\Lexical\LexicalProfileCompiler as PostgreSqlCompiler;
use SqlFaker\PostgreSql\Lexical\LexicalSourceParser as PostgreSqlSourceParser;

/**
 * Builds a PostgreSQL lexical profile from the server's own lexer source.
 *
 * PostgreSQL splits what one release calls a keyword across a keyword list and
 * a scanner, and its parser frontend rewrites some tokens by looking ahead, so
 * the profile records both and is bound to one exact version.
 */
final class PgProfileBuilder
{
    /** @readonly */
    private LexerSource $source;

    /** @readonly */
    private PgCatalogWitnesses $witnesses;

    /** @readonly */
    private PgLexicalCoverage $coverage;

    /**
     * @param LexerSource|null $source Reads the upstream lexer files
     * @param PgCatalogWitnesses|null $witnesses Writes the witnesses the catalogue holds
     * @param PgLexicalCoverage|null $coverage Accounts for the scanner rules they reach
     */
    public function __construct(
        ?LexerSource $source = null,
        ?PgCatalogWitnesses $witnesses = null,
        ?PgLexicalCoverage $coverage = null,
    ) {
        $this->source = $source ?? new UpstreamLexerSource();
        $this->witnesses = $witnesses ?? new PgCatalogWitnesses();
        $this->coverage = $coverage ?? new PgLexicalCoverage();
    }

    /**
     * Reports the upstream files a release keeps its lexer in.
     *
     * PostgreSQL tags its releases with underscores rather than dots, so the
     * version this package uses has to be spelled back into a tag name.
     *
     * @param string $version Release tag, e.g. "pg-17.2"
     *
     * @return array{keywords: string, scanner: string, parser: string} URLs of the three files
     */
    public function sourceUrls(string $version): array
    {
        $release = strtoupper(str_replace(['pg-', '.'], ['REL_', '_'], $version));
        $base = "https://raw.githubusercontent.com/postgres/postgres/refs/tags/{$release}";

        return [
            'keywords' => $base . '/src/include/parser/kwlist.h',
            'scanner' => $base . '/src/backend/parser/scan.l',
            'parser' => $base . '/src/backend/parser/parser.c',
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the upstream source does not describe the lexer
     */
    public function build(string $version): array
    {
        ['keywords' => $keywordUrl, 'scanner' => $scannerUrl, 'parser' => $parserUrl]
            = $this->sourceUrls($version);
        $keywords = $this->source->fetch($keywordUrl);
        $scanner = $this->source->fetch($scannerUrl);
        $parser = $this->source->fetch($parserUrl);

        $profile = [
            'dialect' => 'postgresql',
            'version' => $version,
            'sources' => [
                $keywordUrl => hash('sha256', $keywords),
                $scannerUrl => hash('sha256', $scanner),
                $parserUrl => hash('sha256', $parser),
            ],
            'keywords' => (new PostgreSqlCompiler())->compile($keywords),
            'lookahead' => [
                'FORMAT' => ['token' => 'FORMAT_LA', 'followed_by' => ['JSON']],
                'NOT' => ['token' => 'NOT_LA', 'followed_by' => ['BETWEEN', 'IN_P', 'LIKE', 'ILIKE', 'SIMILAR']],
                'NULLS_P' => ['token' => 'NULLS_LA', 'followed_by' => ['FIRST_P', 'LAST_P']],
                'WITH' => ['token' => 'WITH_LA', 'followed_by' => ['TIME', 'ORDINALITY']],
                'WITHOUT' => ['token' => 'WITHOUT_LA', 'followed_by' => ['TIME']],
            ],
        ];

        $source = (new PostgreSqlSourceParser())->parse($scanner, $parser);
        $lookaheadTokens = array_values(array_map(
            static fn (array $rule): string => $rule['token'],
            $profile['lookahead'],
        ));
        sort($lookaheadTokens);
        if ($lookaheadTokens !== $source['lookahead_tokens']) {
            throw new RuntimeException('PostgreSQL lookahead profile does not match parser.c.');
        }
        $profile['catalog'] = $this->catalog($profile, $source);

        return $profile;
    }

    /**
     * Compiles the catalogue the profile is checked against.
     *
     * @param array<string, mixed> $profile The profile built so far
     * @param array{states: list<string>, rules: list<string>, lookahead_tokens: list<string>} $source What the upstream lexer declares
     *
     * @return array<string, mixed> The catalogue: every witness, and what each scanner rule is reached by
     *
     * @throws RuntimeException When the upstream source does not describe the lexer
     */
    public function catalog(array $profile, array $source): array
    {
        /** @var array<string, list<string>> $keywords */
        $keywords = $profile['keywords'];
        /** @var array<string, array{token: string, followed_by: list<string>}> $lookahead */
        $lookahead = $profile['lookahead'];
        $terminals = $this->witnesses->forProfile($keywords, $lookahead);

        $ruleCount = count($source['rules']) + 1;
        $ruleWitnesses = $this->witnesses->ruleWitnesses();
        if ($ruleWitnesses === [] || max(array_keys($ruleWitnesses)) >= $ruleCount) {
            throw new RuntimeException('PostgreSQL source rule mapping exceeds the parsed rule inventory.');
        }
        foreach ($ruleWitnesses as $rule => $witnessId) {
            $this->witnesses->attachUnit($terminals, $witnessId, 'rule:' . $rule);
        }

        $units = $this->coverage->units($ruleCount, PgCatalogWitnesses::PARSER_MODES);
        $witnessed = $this->coverage->witnessed($terminals);
        $excluded = $this->coverage->excluded($ruleCount);
        $this->coverage->assertCovered($units, $witnessed, $excluded);

        ksort($terminals);
        ksort($witnessed);

        return [
            'source' => [
                'engine' => 'postgresql',
                'entrypoint' => 'scan.l/base_yylex',
                'states' => $source['states'],
                'rules' => $source['rules'],
            ],
            'terminals' => $terminals,
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => $units,
                'witnessed' => $witnessed,
                'excluded' => $excluded,
            ],
        ];
    }

}
