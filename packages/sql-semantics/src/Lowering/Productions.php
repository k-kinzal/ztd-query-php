<?php

declare(strict_types=1);

namespace SqlSemantics\Lowering;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;

/**
 * The productions of one grammar release, spelled as the grammar writes them.
 *
 * A production is identified by its signature: the rule name, a colon, and the
 * symbols of the alternative, terminals by terminal or token-class name, such
 * as `where_opt: WHERE expr` or `where_opt:`. The list is generated from the
 * grammar artifact of the release. Rules dispatch on signatures, so one rule
 * serves every release that has the production, and the list of a release is
 * the complete set of productions its rules must claim.
 *
 * @visibility SqlSemantics
 */
final class Productions
{
    /**
     * @var array<string, self>
     */
    private static array $loaded = [];

    /**
     * @param array<string, list<string>> $signatures The signature of each alternative by rule name
     */
    public function __construct(private readonly array $signatures)
    {
    }

    /**
     * Loads the generated production list of a release, once per file.
     */
    public static function load(string $path): self
    {
        if (!isset(self::$loaded[$path])) {
            Check::invariant(is_file($path), 'The production list of the grammar release is missing: ' . $path);
            $signatures = require $path;
            Check::invariant(is_array($signatures), 'The production list of the grammar release is invalid: ' . $path);
            $checked = [];
            foreach ($signatures as $rule => $alternatives) {
                Check::invariant(is_string($rule) && is_array($alternatives), 'The production list of the grammar release is invalid: ' . $path);
                $list = [];
                foreach ($alternatives as $signature) {
                    Check::invariant(is_string($signature), 'The production list of the grammar release is invalid: ' . $path);
                    $list[] = $signature;
                }
                $checked[$rule] = $list;
            }
            self::$loaded[$path] = new self($checked);
        }

        return self::$loaded[$path];
    }

    /**
     * Answers the typed view of the production a parse tree node matched.
     */
    public function form(Node $node): Form
    {
        return new Form($node, $this->signature($node));
    }

    /**
     * Answers the signature of the production a parse tree node matched.
     */
    public function signature(Node $node): string
    {
        $signature = $this->signatures[$node->name][$node->ordinal] ?? null;
        Check::invariant($signature !== null, 'The grammar release has no production ' . $node->name . '#' . $node->ordinal . '.');

        return $signature;
    }

    /**
     * Answers every signature of the release.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return array_merge(...array_values($this->signatures));
    }
}
