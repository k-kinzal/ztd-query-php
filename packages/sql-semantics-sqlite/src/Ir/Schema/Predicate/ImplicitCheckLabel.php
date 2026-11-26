<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The checked diagnostic presentation of an unnamed comparison.
 *
 * @visibility public
 * @example Retaining spaces, the comparison alias and decimal zeroes
 *     $label = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ImplicitCheckLabel(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('Id'), \SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ComparisonSpelling::DoubleEqual, false, ' ', ' ', 1);
 *     $label->expression(new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1')) // => 'Id == 01'
 */
final class ImplicitCheckLabel
{
    use Snapshot;

    private const NON_COLUMN_KEYWORDS = [
        'add' => true,
        'all' => true,
        'alter' => true,
        'and' => true,
        'as' => true,
        'autoincrement' => true,
        'between' => true,
        'case' => true,
        'cast' => true,
        'check' => true,
        'collate' => true,
        'commit' => true,
        'constraint' => true,
        'create' => true,
        'current_date' => true,
        'current_time' => true,
        'current_timestamp' => true,
        'default' => true,
        'deferrable' => true,
        'delete' => true,
        'distinct' => true,
        'drop' => true,
        'else' => true,
        'escape' => true,
        'except' => true,
        'exists' => true,
        'foreign' => true,
        'from' => true,
        'group' => true,
        'having' => true,
        'in' => true,
        'index' => true,
        'insert' => true,
        'intersect' => true,
        'into' => true,
        'is' => true,
        'isnull' => true,
        'join' => true,
        'limit' => true,
        'not' => true,
        'nothing' => true,
        'notnull' => true,
        'null' => true,
        'on' => true,
        'or' => true,
        'order' => true,
        'primary' => true,
        'raise' => true,
        'references' => true,
        'returning' => true,
        'select' => true,
        'set' => true,
        'table' => true,
        'then' => true,
        'to' => true,
        'transaction' => true,
        'union' => true,
        'unique' => true,
        'update' => true,
        'using' => true,
        'values' => true,
        'when' => true,
        'where' => true,
    ];

    /**
     * Checks the lexical data that SQLite includes in a native failure label.
     */
    public function __construct(
        public readonly Identifier $columnName,
        public readonly ComparisonSpelling $token,
        public readonly bool $quoted,
        public readonly string $beforeOperator = ' ',
        public readonly string $afterOperator = ' ',
        public readonly int $leadingZeroes = 0,
    ) {
        Check::input(
            $quoted || (preg_match('/\A[A-Za-z_\x80-\xff][A-Za-z0-9_$\x80-\xff]*\z/', $columnName->value) === 1
                && !isset(self::NON_COLUMN_KEYWORDS[$columnName->key()])),
            'An implicit CHECK needs a checked bare column spelling.',
        );
        Check::input(
            preg_match('/\A[ \t\n\r\f]{0,128}\z/', $beforeOperator) === 1
                && preg_match('/\A[ \t\n\r\f]{0,128}\z/', $afterOperator) === 1,
            'An implicit CHECK gap must be within the whitespace profile.',
        );
        Check::input($leadingZeroes >= 0 && $leadingZeroes <= 32, 'An implicit CHECK decimal prefix must be within the profile.');
    }

    /**
     * Resolves the operation denoted by the diagnostic token.
     */
    public function operation(): ComparisonOperator
    {
        return match ($this->token) {
            ComparisonSpelling::Equal, ComparisonSpelling::DoubleEqual => ComparisonOperator::Equal,
            ComparisonSpelling::NotEqual, ComparisonSpelling::BangEqual => ComparisonOperator::NotEqual,
            ComparisonSpelling::Less => ComparisonOperator::Less,
            ComparisonSpelling::LessOrEqual => ComparisonOperator::LessOrEqual,
            ComparisonSpelling::Greater => ComparisonOperator::Greater,
            ComparisonSpelling::GreaterOrEqual => ComparisonOperator::GreaterOrEqual,
        };
    }

    /**
     * Emits the comparison from the checked presentation and its integer value.
     */
    public function expression(IntegerLiteral $value): string
    {
        Check::input(!str_starts_with($value->decimal, '-'), 'An implicit CHECK label needs a nonnegative integer.');
        $column = $this->quoted ? $this->columnName->quoted() : $this->columnName->value;

        return $column . $this->beforeOperator . $this->token->value . $this->afterOperator
            . str_repeat('0', $this->leadingZeroes) . $value->decimal;
    }
}
