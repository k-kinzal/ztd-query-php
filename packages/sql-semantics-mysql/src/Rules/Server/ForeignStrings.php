<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Text\NameEncoding;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\DropServer;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives system-character conversion for foreign-server names and options.
 *
 * Names already hold decoded Unicode; option text holds client bytes. Conversion
 * notices precede execution, including duplicate-name and missing-server errors.
 * Every written option contributes, even when a later option replaces its value.
 * Observed with utf8mb4 clients on MySQL 5.6, 5.7 and 8.4.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ForeignStrings
{
    /**
     * Records conversion notices in operand order, independently of execution.
     */
    public function derive(CreateServer|AlterServer|DropServer $statement, Derivation $derivation): void
    {
        $values = [$statement->name->value];
        if ($statement instanceof CreateServer) {
            $values[] = $statement->wrapper->value;
        }
        $client = Settings::of($derivation->context)->client;
        foreach ($statement instanceof DropServer ? [] : $statement->options as $option) {
            if ($option->value instanceof Text) {
                $values[] = (new NameEncoding())->decode($option->value->value, $client);
            }
        }
        foreach ($values as $value) {
            $warning = $this->conversion($value, $derivation->context->profile->grammar);
            if ($warning !== null) {
                $derivation->warn($warning, $statement);
            }
        }
    }

    /**
     * Describes supplementary characters that the system character set replaces.
     */
    public function conversion(string $decoded, GrammarRelease $release): ?NameConversion
    {
        return preg_match('/[\xF0-\xF4][\x80-\xBF]{3}/', $decoded) === 1
            ? new NameConversion(new Name($decoded), in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true))
            : null;
    }
}
