<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Extension;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Registers a procedural language by its call handler, with an optional inline handler and validator.
 * PROCEDURAL is noise; NO VALIDATOR and an omitted validator both register none.
 * @visibility public
 * @example Reading the language handlers
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TRUSTED LANGUAGE plsample HANDLER app.plsample_call INLINE plsample_inline VALIDATOR plsample_check');
 *     $statement->name // => 'plsample'
 *     $statement->trusted // => true
 *     $statement->handler->parts // => ['app', 'plsample_call']
 *     $statement->validator->parts // => ['plsample_check']
 * @example Rejecting an overqualified handler
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE plsample HANDLER plsample_call');
 *     $statement->withHandler(new \SqlSemantics\Model\Relation\QualifiedName(['a', 'b', 'c', 'd'])); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateLanguageStatement extends BoundStatement
{
    /**
     * @param bool $orReplace Whether an existing language definition is replaced (OR REPLACE)
     * @param bool $trusted Whether unprivileged users may use the language (TRUSTED)
     * @param QualifiedName $handler Call handler function (HANDLER)
     * @param QualifiedName|null $inline Handler of anonymous code blocks (INLINE)
     * @param QualifiedName|null $validator Function checking new function definitions (VALIDATOR)
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly bool $orReplace,
        public readonly bool $trusted,
        public readonly QualifiedName $handler,
        public readonly ?QualifiedName $inline,
        public readonly ?QualifiedName $validator,
    ) {
        ExtensionInvariant::dialect($origin);
        ExtensionInvariant::names($name);
        ExtensionInvariant::function($handler);
        ExtensionInvariant::function($inline);
        ExtensionInvariant::function($validator);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the language definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->orReplace, $this->trusted, $this->handler, $this->inline, $this->validator);
    }

    /**
     * Replaces the language name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->orReplace, $this->trusted, $this->handler, $this->inline, $this->validator));
    }

    /**
     * Replaces whether an existing definition is replaced.
     */
    public function withOrReplace(bool $orReplace): self
    {
        return $this->changed(new self($this->origin, $this->name, $orReplace, $this->trusted, $this->handler, $this->inline, $this->validator));
    }

    /**
     * Replaces whether the language is trusted.
     */
    public function withTrusted(bool $trusted): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->orReplace, $trusted, $this->handler, $this->inline, $this->validator));
    }

    /**
     * Replaces the call handler function.
     */
    public function withHandler(QualifiedName $handler): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->orReplace, $this->trusted, $handler, $this->inline, $this->validator));
    }

    /**
     * Replaces the inline handler; null registers none.
     */
    public function withInline(?QualifiedName $inline): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->orReplace, $this->trusted, $this->handler, $inline, $this->validator));
    }

    /**
     * Replaces the validator; null registers none.
     */
    public function withValidator(?QualifiedName $validator): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->orReplace, $this->trusted, $this->handler, $this->inline, $validator));
    }
}
