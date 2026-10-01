<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Declaration\TypeDeclaration;

/**
 * Reads a type through its column grammar and verifies that it consumes the entire input.
 * @visibility SqlSemantics
 */
final class TypeInput
{
    /**
     * Uses the resolved language for every read.
     */
    public function __construct(private readonly Language $language)
    {
    }

    /**
     * @throws AnalysisException When the input contains anything beyond one type
     * @throws \SqlSemantics\Core\SemanticException When the declared type is invalid
     */
    public function read(string $sql): TypeDeclaration
    {
        try {
            $tree = $this->language->parser()->parse('CREATE TABLE __type_input (__value ' . $sql . "\n)");
            $types = Tree::outer($tree, $this->language->dialect->platform()->syntax()->nodes('declaredType'));
            $input = array_values(array_filter($this->language->parser()->tokenize($sql), static fn (Token $token): bool => $token->text !== ''));
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
        $spelling = static fn (Token $token): string => $token->text;
        if (count($types) !== 1 || array_map($spelling, $types[0]->tokens()) !== array_map($spelling, $input)) {
            throw new AnalysisException('Expected exactly one declared type, without column attributes.');
        }
        $values = $this->language->values();
        $declaration = (new TypeReader($this->language->dialect))->read($types[0], $values);
        return new TypeDeclaration($declaration->type, $declaration->autoIncrement, $declaration->notNull, $declaration->unique, $values->read($types[0]));
    }
}
