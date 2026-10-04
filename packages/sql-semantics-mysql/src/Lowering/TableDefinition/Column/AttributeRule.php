<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyOptionRule;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyRule;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CommentAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\FormatAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnFormat;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OnUpdate;
use SqlSemantics\Platform\MySql\Statement\Table\Column\SridAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\StorageAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the attributes of a column definition.
 *
 * Rule: MYSQL-COLUMN-ATTRIBUTE-001. Scope: attribute (5.x), gcol_attribute
 * (5.7), column_attribute (8.0 and later), column_format, storage_media,
 * now_or_signed_literal, opt_primary. `KEY` alone is PRIMARY KEY and `UNIQUE
 * KEY` is UNIQUE (create-table.html: "KEY, when used alone in a column
 * definition, is a synonym for PRIMARY KEY"; "UNIQUE [KEY]"). Constructs:
 * the ColumnAttribute classes and CheckConstraint. Terminates: every child
 * is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AttributeRule
{
    /**
     * The attributes written as keywords alone.
     */
    private const KEYWORDS = [
        'attribute: NULL_SYM' => ColumnKeyword::Null, 'column_attribute: NULL_SYM' => ColumnKeyword::Null, 'gcol_attribute: NULL_SYM' => ColumnKeyword::Null,
        'attribute: not NULL_SYM' => ColumnKeyword::NotNull, 'column_attribute: not NULL_SYM' => ColumnKeyword::NotNull, 'gcol_attribute: not NULL_SYM' => ColumnKeyword::NotNull,
        'column_attribute: not SECONDARY_SYM' => ColumnKeyword::NotSecondary, 'attribute: AUTO_INC' => ColumnKeyword::AutoIncrement,
        'column_attribute: AUTO_INC' => ColumnKeyword::AutoIncrement, 'attribute: SERIAL_SYM DEFAULT VALUE_SYM' => ColumnKeyword::SerialDefaultValue,
        'column_attribute: SERIAL_SYM DEFAULT_SYM VALUE_SYM' => ColumnKeyword::SerialDefaultValue, 'attribute: opt_primary KEY_SYM' => ColumnKeyword::PrimaryKey,
        'column_attribute: opt_primary KEY_SYM' => ColumnKeyword::PrimaryKey, 'gcol_attribute: opt_primary KEY_SYM' => ColumnKeyword::PrimaryKey,
        'attribute: UNIQUE_SYM' => ColumnKeyword::Unique, 'attribute: UNIQUE_SYM KEY_SYM' => ColumnKeyword::Unique, 'column_attribute: UNIQUE_SYM' => ColumnKeyword::Unique,
        'column_attribute: UNIQUE_SYM KEY_SYM' => ColumnKeyword::Unique, 'gcol_attribute: UNIQUE_SYM' => ColumnKeyword::Unique, 'gcol_attribute: UNIQUE_SYM KEY_SYM' => ColumnKeyword::Unique,
    ];

    /**
     * The storage formats and media written as keywords.
     */
    private const FORMATS = [
        'attribute: COLUMN_FORMAT_SYM DEFAULT' => ColumnFormat::Default, 'attribute: COLUMN_FORMAT_SYM FIXED_SYM' => ColumnFormat::Fixed,
        'attribute: COLUMN_FORMAT_SYM DYNAMIC_SYM' => ColumnFormat::Dynamic, 'column_format: DEFAULT_SYM' => ColumnFormat::Default,
        'column_format: FIXED_SYM' => ColumnFormat::Fixed, 'column_format: DYNAMIC_SYM' => ColumnFormat::Dynamic,
        'attribute: STORAGE_SYM DEFAULT' => StorageMedium::Default, 'attribute: STORAGE_SYM DISK_SYM' => StorageMedium::Disk,
        'attribute: STORAGE_SYM MEMORY_SYM' => StorageMedium::Memory, 'storage_media: DEFAULT_SYM' => StorageMedium::Default,
        'storage_media: DISK_SYM' => StorageMedium::Disk, 'storage_media: MEMORY_SYM' => StorageMedium::Memory,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one attribute: a node of `attribute`, `gcol_attribute` or `column_attribute`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function attribute(Node $attribute): ColumnAttribute
    {
        $form = $this->lowering->productions->form($attribute);
        $keyword = self::KEYWORDS[$form->signature] ?? null;
        if ($keyword !== null) {
            $this->marks($form);

            return new KeywordAttribute($keyword);
        }
        $format = self::FORMATS[$form->signature] ?? null;
        if ($format !== null) {
            return $format instanceof ColumnFormat ? new FormatAttribute($format) : new StorageAttribute($format);
        }

        return $this->valued($form);
    }

    /**
     * Lowers an attribute that holds a value.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function valued(Form $form): ColumnAttribute
    {
        $literals = $this->lowering->literals;

        return match ($form->signature) {
            'attribute: DEFAULT now_or_signed_literal', 'column_attribute: DEFAULT_SYM now_or_signed_literal' => new DefaultLiteral($this->value($form->node(1))),
            'column_attribute: DEFAULT_SYM ( expr )' => new DefaultExpression($this->lowering->expressions->expression($form->node(2))),
            'attribute: ON UPDATE_SYM now', 'column_attribute: ON_SYM UPDATE_SYM now' => new OnUpdate($this->lowering->calls->currentTimestamp($form->node(2))),
            'attribute: COMMENT_SYM TEXT_STRING_sys', 'column_attribute: COMMENT_SYM TEXT_STRING_sys', 'gcol_attribute: COMMENT_SYM TEXT_STRING_sys' => new CommentAttribute($literals->text($form->node(1))),
            'attribute: COLLATE_SYM collation_name', 'column_attribute: COLLATE_SYM collation_name' => new CollateAttribute($this->collation($form->node(1))),
            'column_attribute: COLUMN_FORMAT_SYM column_format', 'column_attribute: STORAGE_SYM storage_media' => $this->attribute($form->node(1)),
            'column_attribute: SRID_SYM real_ulonglong_num' => new SridAttribute($this->lowering->numbers->numeral($form->node(1))),
            'column_attribute: opt_constraint_name check_constraint' => new CheckConstraint((new KeyRule($this->lowering))->check($form->node(1)), (new KeyRule($this->lowering))->constraint($form->node(0))),
            'column_attribute: constraint_enforcement' => new EnforcementAttribute((new KeyRule($this->lowering))->enforced($form->node(0))),
            'column_attribute: ENGINE_ATTRIBUTE_SYM opt_equal json_attribute' => new EngineAttribute($literals->text($form->node(2))),
            'column_attribute: SECONDARY_ENGINE_ATTRIBUTE_SYM opt_equal json_attribute' => new EngineAttribute($literals->text($form->node(2)), true),
            'column_attribute: visibility' => new KeywordAttribute((new KeyOptionRule($this->lowering))->visible($form->node(0)) ? ColumnKeyword::Visible : ColumnKeyword::Invisible),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Confirms the keyword rules inside a keyword attribute: `not` and `opt_primary`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function marks(Form $form): void
    {
        foreach ($form->node->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            $mark = $this->lowering->productions->form($child);
            if ($mark->signature !== 'opt_primary:' && $mark->signature !== 'opt_primary: PRIMARY_SYM') {
                $this->lowering->options->skip($child);
            }
        }
    }

    /**
     * Lowers the value of a literal default: a node of `now_or_signed_literal`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function value(Node $value): Scalar
    {
        $form = $this->lowering->productions->form($value);

        return match ($form->signature) {
            'now_or_signed_literal: now' => $this->lowering->calls->currentTimestamp($form->node(0)),
            'now_or_signed_literal: signed_literal', 'now_or_signed_literal: signed_literal_or_null' => $this->lowering->literals->literal($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a collation name that must be present.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function collation(Node $name): CollationName
    {
        $collation = $this->lowering->charsets->collation($name);
        Check::invariant($collation !== null, 'A collation_name names a collation.');

        return $collation;
    }
}
