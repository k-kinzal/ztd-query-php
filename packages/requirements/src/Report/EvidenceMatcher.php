<?php

declare(strict_types=1);

namespace Requirements\Report;

use Requirements\Input\InvalidInputException;
use Requirements\Model\Excerpt;
use Requirements\Model\Source;
use Requirements\Source\Registry;
use Requirements\Source\Unit;
use RuntimeException;

/**
 * Resolves an evidence entry to the one unit of its item's scope that it quotes completely.
 */
final class EvidenceMatcher
{
    /**
     * @param Registry $registry The source extensions of the project
     * @param string $directory The configuration directory
     * @param bool $live Whether to read the current source URIs instead of pinned snapshots
     */
    public function __construct(private readonly Registry $registry, private readonly string $directory, private readonly bool $live)
    {
    }

    /**
     * Resolves one evidence entry.
     *
     * @param Source $source The source of the item quoting it
     * @param Excerpt $excerpt The evidence entry
     * @param array<string, list<string>> $scopes The unit keys of each scope by source ID
     *
     * @return string The key of the quoted unit
     *
     * @throws InvalidInputException When the item's source format has no extension
     * @throws RuntimeException When the selector matches no or several units, lies outside the scope or the quotation differs
     */
    public function match(Source $source, Excerpt $excerpt, array $scopes): string
    {
        $matches = $this->registry->get($source->format)->select($source, $excerpt->selector, $this->directory, $this->live);
        if (count($matches) !== 1) {
            throw new RuntimeException('Evidence must select exactly one unit: ' . $excerpt->selector);
        }
        $key = $matches[0]->key($source);
        if (!in_array($key, $scopes[$source->id], true)) {
            throw new RuntimeException('Evidence is outside the declared scope: ' . $excerpt->selector);
        }
        if (Unit::normalize($excerpt->quote) !== Unit::normalize($matches[0]->text)) {
            throw new RuntimeException('Quotation differs from the complete source unit: ' . $excerpt->selector);
        }
        return $key;
    }
}
