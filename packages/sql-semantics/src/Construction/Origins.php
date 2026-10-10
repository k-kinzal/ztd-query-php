<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use SqlParser\Parser\Node as ParseNode;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Source\Origin;
use SqlSemantics\Statement\Source\SourceMap;

/**
 * Records original byte ranges while a parse tree is lowered, then publishes an immutable map.
 *
 * @visibility SqlSemantics
 */
final class Origins
{
    /**
     * @var array<int, Origin> Locations by semantic occurrence identity
     */
    private array $origins = [];

    /**
     * Records the range from the first through the last source token, excluding empty synthetic tokens, and returns the occurrence.
     *
     * @template T of Node
     * @param T $value The lowered occurrence
     * @return T
     */
    public function record(Node $value, ParseNode $source): Node
    {
        $tokens = array_values(array_filter($source->tokens(), static fn (\SqlParser\Lexer\Token $token): bool => $token->text !== ''));
        $first = $tokens[0] ?? null;
        $last = $tokens[count($tokens) - 1] ?? null;
        if ($first !== null && $last !== null) {
            $this->origins[spl_object_id($value)] = new Origin($value, $first->offset, $last->offset + strlen($last->text) - $first->offset);
        }

        return $value;
    }

    /**
     * Publishes a snapshot that later recordings cannot change.
     */
    public function publish(): SourceMap
    {
        return new SourceMap(array_values($this->origins));
    }
}
