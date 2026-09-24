<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Definition;

/**
 * An attribute of a base type definition and of ALTER TYPE ... SET.
 * @visibility public
 * @example Choosing a storage strategy
 *     \SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute::Storage->choose('MAIN') // => 'main'
 *     \SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute::Alignment->choose('pg_catalog.float8') // => 'double'
 */
enum BaseTypeAttribute: string implements DefinitionAttribute
{
    case Like = 'LIKE';
    case InternalLength = 'INTERNALLENGTH';
    case Input = 'INPUT';
    case Output = 'OUTPUT';
    case Receive = 'RECEIVE';
    case Send = 'SEND';
    case TypmodIn = 'TYPMOD_IN';
    case TypmodOut = 'TYPMOD_OUT';
    case Analyze = 'ANALYZE';
    case Subscript = 'SUBSCRIPT';
    case Category = 'CATEGORY';
    case Preferred = 'PREFERRED';
    case Delimiter = 'DELIMITER';
    case Element = 'ELEMENT';
    case Default = 'DEFAULT';
    case Alignment = 'ALIGNMENT';
    case Storage = 'STORAGE';
    case Collatable = 'COLLATABLE';
    case PassedByValue = 'PASSEDBYVALUE';

    /**
     * Alignment spellings the server accepts, by canonical alignment.
     */
    public const ALIGNMENTS = ['double' => ['double', 'float8', 'pg_catalog.float8'], 'int4' => ['int4', 'pg_catalog.int4'], 'int2' => ['int2', 'pg_catalog.int2'], 'char' => ['char', 'pg_catalog.bpchar']];

    /**
     * The attribute name as written in SQL.
     */
    public function spelling(): string
    {
        return $this->value;
    }

    /**
     * Support functions are names, LIKE and ELEMENT are types, and the remaining attributes are flags, a length, text, or keywords.
     */
    public function kind(): DefinitionKind
    {
        return match ($this) {
            self::Input, self::Output, self::Receive, self::Send, self::TypmodIn, self::TypmodOut, self::Analyze, self::Subscript => DefinitionKind::Name,
            self::Like, self::Element => DefinitionKind::Type,
            self::InternalLength => DefinitionKind::Length,
            self::Category, self::Delimiter, self::Default => DefinitionKind::Text,
            self::Preferred, self::Collatable, self::PassedByValue => DefinitionKind::Boolean,
            self::Alignment, self::Storage => DefinitionKind::Choice,
        };
    }

    /**
     * Alignment and storage keywords match case-insensitively.
     */
    public function choose(string $text): ?string
    {
        $text = strtolower($text);
        if ($this === self::Storage) {
            return in_array($text, ['plain', 'external', 'extended', 'main'], true) ? $text : null;
        }
        foreach ($this === self::Alignment ? self::ALIGNMENTS : [] as $alignment => $spellings) {
            if (in_array($text, $spellings, true)) {
                return $alignment;
            }
        }
        return null;
    }

    /**
     * ALTER TYPE ... SET changes only the optional support functions and the storage strategy.
     */
    public function alterable(): bool
    {
        return in_array($this, [self::Receive, self::Send, self::TypmodIn, self::TypmodOut, self::Analyze, self::Subscript, self::Storage], true);
    }
}
