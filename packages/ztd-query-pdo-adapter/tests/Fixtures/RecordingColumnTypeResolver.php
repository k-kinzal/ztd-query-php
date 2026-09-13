<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use LogicException;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

/**
 * A column type resolver that answers from a list and remembers what it was asked.
 *
 * A test that needs to know which driver metadata reached the resolver can read
 * it off afterwards, rather than describing it as an expectation the older
 * PHPUnit majors this package supports have no way to seal.
 */
final class RecordingColumnTypeResolver implements ResultColumnTypeResolver
{
    /**
     * Metadata this resolver was handed, in the order it arrived.
     *
     * @var list<array<string, mixed>>
     */
    public array $metadataSeen = [];

    /**
     * @var list<ColumnType> Declarations to answer with, one per call
     */
    private array $answers;

    /**
     * Binds the resolver to what it answers.
     *
     * @param ColumnType ...$answers One declaration per expected call, in order
     */
    public function __construct(ColumnType ...$answers)
    {
        $this->answers = array_values($answers);
    }

    /**
     * Answers the next declaration, and remembers the metadata it was given.
     *
     * @param array<string, mixed> $metadata Driver metadata for one column
     *
     * @return ColumnType The declaration standing at this call's position
     *
     * @throws LogicException When asked more times than it was given answers
     */
    public function resolve(array $metadata): ColumnType
    {
        $this->metadataSeen[] = $metadata;
        $answer = $this->answers[count($this->metadataSeen) - 1] ?? null;
        if ($answer === null) {
            throw new LogicException(sprintf('The resolver was asked %d times but was given %d answers.', count($this->metadataSeen), count($this->answers)));
        }

        return $answer;
    }
}
