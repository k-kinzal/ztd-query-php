<?php

declare(strict_types=1);

namespace BisonParser\Ast;

/**
 * A type tag written between angle brackets.
 *
 * Besides a named tag such as `<int>`, a `%destructor` or `%printer` may
 * name `<*>`, every tagged symbol, and `<>`, every untagged symbol. They
 * are kept as Bison keeps them: `*` and the empty string.
 *
 * @visibility public
 *
 * @example Reading a tag
 *     $tag = new \BisonParser\Ast\Tag('std::vector<int>', new \BisonParser\Ast\Location(1, 8));
 *     $tag->name // => 'std::vector<int>'
 *     $tag->isAny() // => false
 */
final class Tag
{
    /**
     * The name of the tag that stands for every tagged symbol.
     */
    public const ANY = '*';

    /**
     * The name of the tag that stands for every untagged symbol.
     */
    public const NONE = '';

    /**
     * @param string $name Text between the angle brackets
     * @param Location $location Where the tag is written
     */
    public function __construct(
        public readonly string $name,
        public readonly Location $location,
    ) {
    }

    /**
     * Reports whether the tag is `<*>`.
     *
     * @return bool True for the tag of every tagged symbol
     */
    public function isAny(): bool
    {
        return $this->name === self::ANY;
    }

    /**
     * Reports whether the tag is `<>`.
     *
     * @return bool True for the tag of every untagged symbol
     */
    public function isNone(): bool
    {
        return $this->name === self::NONE;
    }
}
