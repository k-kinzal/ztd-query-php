<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Alignment;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Choice;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\CollationProvider;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\FinalModification;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Parallelism;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\StorageStrategy;

/**
 * How a command reads the value of one of its definition attributes.
 *
 * PostgreSQL's commands read a `DefElem` value with one of the `defGet*`
 * functions of `src/backend/commands/define.c`: as the name of an object
 * (`defGetQualifiedName`), as a type name (`defGetTypeName`), as a Boolean,
 * an integer, a type length or text, or as text compared with a fixed set of
 * words. The obsolete operator attributes `sort1`, `sort2`, `ltcmp` and
 * `gtcmp` are recognized without reading their value.
 * Source: https://www.postgresql.org/docs/17/sql-createoperator.html, https://www.postgresql.org/docs/17/sql-createaggregate.html,
 * https://www.postgresql.org/docs/17/sql-createtype.html, `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Telling what kind of object a function attribute names
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Function->kind() // => \SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind::Function
 */
enum Reading
{
    case Function;
    case Operator;
    case OperatorClass;
    case Collation;
    case TextSearchParser;
    case TextSearchTemplate;
    case TextSearchConfiguration;
    case CreatedType;
    case Type;
    case Boolean;
    case Integer;
    case Text;
    case Length;
    case Parallelism;
    case FinalModification;
    case Alignment;
    case Storage;
    case Provider;
    case Ignored;

    /**
     * The kind of object each name reading looks up; a created type is the name of the type the command creates.
     */
    private const KINDS = [
        'Function' => ObjectKind::Function,
        'Operator' => ObjectKind::Operator,
        'OperatorClass' => ObjectKind::OperatorClass,
        'Collation' => ObjectKind::Collation,
        'TextSearchParser' => ObjectKind::TextSearchParser,
        'TextSearchTemplate' => ObjectKind::TextSearchTemplate,
        'TextSearchConfiguration' => ObjectKind::TextSearchConfiguration,
        'CreatedType' => ObjectKind::Type,
    ];

    /**
     * The set of words each choice reading compares the text with.
     */
    private const CHOICES = [
        'Parallelism' => Parallelism::class,
        'FinalModification' => FinalModification::class,
        'Alignment' => Alignment::class,
        'Storage' => StorageStrategy::class,
        'Provider' => CollationProvider::class,
    ];

    /**
     * Answers the kinds of object a value can be read as the name of.
     *
     * @return list<ObjectKind>
     */
    public static function kinds(): array
    {
        return array_values(self::KINDS);
    }

    /**
     * Answers the kind of object the value names, or null when the value is not read as a name.
     */
    public function kind(): ?ObjectKind
    {
        return self::KINDS[$this->name] ?? null;
    }

    /**
     * Answers the set of words the value is compared with, or null when the value is not read as a choice.
     *
     * @return class-string<Choice>|null
     */
    public function choices(): ?string
    {
        return self::CHOICES[$this->name] ?? null;
    }
}
