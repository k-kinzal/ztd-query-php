<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\ResourceGroup;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupOptions;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Assigns the current thread, or the listed threads, to a resource group.
 * @visibility public
 * @example Reading the assigned threads
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SET RESOURCE GROUP batch FOR 14, 0x1f');
 *     [$statement->name, array_map(static fn ($thread) => $thread->text, $statement->threads)] // => ['batch', ['14', '0x1f']]
 */
final class SetResourceGroupStatement extends BoundStatement
{
    /**
     * @param list<Literal> $threads Thread identifiers as unsigned integer literals; empty assigns the current thread
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly array $threads = [])
    {
        ResourceGroupOptions::validate($origin, $name);
        Collections::objects($threads, Literal::class);
        foreach ($threads as $thread) {
            if ($thread->type->dialect !== $origin->dialect || !in_array($thread->literalKind, [LiteralKind::Number, LiteralKind::Binary], true) || !ctype_xdigit(str_replace(['0x', '0X'], '', $thread->text))) {
                throw new InvalidStructure('A thread identifier is an unsigned integer literal.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**
     * Retains the assignment while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->threads);
    }

    /**
     * Assigns the threads to another group.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->threads));
    }

    /**
     * Replaces the assigned threads; an empty list assigns the current thread.
     * @param list<Literal> $threads
     */
    public function withThreads(array $threads): self
    {
        return $this->changed(new self($this->origin, $this->name, $threads));
    }
}
