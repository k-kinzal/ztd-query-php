<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

/**
 * Which function body the analyzer is currently walking.
 *
 * @visibility root
 */
final class FunctionScope
{
    /**
     * The name used for statements issued outside any function.
     */
    public const MAIN = '{main}';

    /**
     * @param string $file The file being analyzed, relative to the analysis root
     * @param string $function The enclosing function, as `Class::method`, `function` or `{main}`
     * @param string|null $className The class the body belongs to, when it belongs to one
     * @param list<string> $stack The functions currently being followed, innermost last
     */
    public function __construct(
        public readonly string $file,
        public readonly string $function = self::MAIN,
        public readonly ?string $className = null,
        public readonly array $stack = [],
    ) {
    }

    /**
     * The same scope, moved into the named function.
     *
     * @param string $function The function being followed
     * @param string|null $className The class it belongs to
     * @param string $file The file the followed body is written in
     */
    public function enter(string $function, ?string $className, string $file): self
    {
        return new self(
            $file === '' ? $this->file : $file,
            $function,
            $className,
            array_merge($this->stack, [$function]),
        );
    }

    /**
     * How many calls deep the analyzer currently is.
     */
    public function depth(): int
    {
        return count($this->stack);
    }

    /**
     * Whether the named function is already being followed, which would recurse.
     */
    public function isFollowing(string $function): bool
    {
        return in_array($function, $this->stack, true);
    }
}
