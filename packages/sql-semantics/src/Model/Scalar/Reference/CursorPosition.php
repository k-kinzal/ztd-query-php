<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * CursorPosition has explicit semantic operands and a fixed expression category.
 * @visibility public
 * @example Reading the cursor name
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UPDATE t SET id = 1 WHERE CURRENT OF cur');
 *     $statement->where->cursor[0] // => 'cur'
 */
final class CursorPosition extends Expression
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $cursor;

    /**
     * @param list<string> $cursor
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        array $cursor,
    ) {
        \SqlSemantics\Model\Validation\Collections::strings($cursor);
        if ($cursor === [] || in_array('', $cursor, true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An identifier requires nonempty name parts.');
        }
        $this->cursor = $cursor;
        \SqlSemantics\Model\Validation\Collections::strings($cursor);
        parent::__construct($facts, $source);
        foreach ($this->inputs() as $input) {
            if ($input->type->dialect !== $facts->type->dialect) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('Expression operands cannot mix SQL dialects.');
            }
        }
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::CurrentRow;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return 'CURRENT OF';
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->cursor);
    }

    /**
     * Returns unquoted identifier parts that identify this reference.
     */
    #[Override]
    public function referenceParts(): array
    {
        return $this->cursor;
    }
}
