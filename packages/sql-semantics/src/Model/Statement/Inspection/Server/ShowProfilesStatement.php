<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Server;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Session\ProfileField;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists the most recent profiled statements of the session.
 * @visibility public
 * @example Inspecting the result fields
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PROFILES');
 *     array_column($statement->resultColumns(), 'name') // => ['Query_ID', 'Duration', 'Query']
 */
final class ShowProfilesStatement extends InspectionStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin);
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(ProfileField::summary());
    }
}
