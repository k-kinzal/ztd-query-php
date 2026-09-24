<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A string option such as SOURCE_HOST = 'db1', with the literal kept as written; SOURCE_TLS_CIPHERSUITES may also be NULL.
 * @visibility public
 * @example Reading a host
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'db1'");
 *     $statement->settings[0]->value->text // => "'db1'"
 */
final class SourceText implements SourceSetting
{
    /**
     * Requires a string option and a quoted MySQL string; every option except SOURCE_COMPRESSION_ALGORITHMS is single-line.
     * @throws InvalidStructure
     */
    public function __construct(public readonly SourceOption $option, public readonly Literal $value)
    {
        if (!in_array($option->value, SourceOption::TEXTS, true)) {
            throw new InvalidStructure($option->value . ' does not take a string.');
        }
        if ($option !== SourceOption::TlsCiphersuites || $value->literalKind !== LiteralKind::Null) {
            ReplicationText::check($value, $option->value, $option !== SourceOption::CompressionAlgorithms);
        }
    }

    /**
     * Names the option the setting assigns.
     */
    public function option(): SourceOption
    {
        return $this->option;
    }
}
