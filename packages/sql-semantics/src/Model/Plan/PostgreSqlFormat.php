<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

/**
 * The selected PostgreSqlFormat instruction.
 * @visibility public
 */
enum PostgreSqlFormat: string
{
    case Text = 'TEXT';
    case Json = 'JSON';
    case Xml = 'XML';
    case Yaml = 'YAML';
}
