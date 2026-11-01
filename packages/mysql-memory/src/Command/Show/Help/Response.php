<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Help;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\ResultSet;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * A HELP response with its content-dependent column shape.
 *
 * @visibility MySqlMemory
 */
final class Response
{
    /**
     * @param array<string, int> $columns The column names and character lengths
     * @param list<list<string>> $rows The text rows
     */
    public function __construct(public readonly array $columns, public readonly array $rows)
    {
    }

    /**
     * Sends the response in the client's result character set.
     */
    public function sent(Context $context): ResultSet
    {
        $headings = [];
        foreach ($this->columns as $name => $length) {
            $headings[] = Heading::text($name, Field::VarString, $length, ColumnFlag::NotNull->value, 31);
        }

        return (new Listing($headings))->sent($this->rows, $context);
    }
}
