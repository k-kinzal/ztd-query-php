<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Procedural;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Searches the server help tables for a topic; the result layout depends on how many topics match.
 * @visibility public
 * @example Reading the requested topic
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("HELP 'contents'");
 *     [$statement instanceof \SqlSemantics\Model\Statement\Procedural\HelpStatement, $statement->topic] // => [true, 'contents']
 */
final class HelpStatement extends BoundStatement
{
    /**
     * @param string $topic Searched topic text; an identifier and a string spell the same search
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $topic)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('HELP requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Help;
    }

    /**
     * Retains the searched topic while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->topic);
    }

    /**
     * Searches another topic.
     */
    public function withTopic(string $topic): self
    {
        return $this->changed(new self($this->origin, $topic));
    }
}
