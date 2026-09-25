<?php

declare(strict_types=1);

namespace Requirements\Tests\Fixtures\Examples\Extensions;

use Requirements\Model\Source;
use Requirements\Source\ResourceLoader;
use Requirements\Source\SourceExtension;
use Requirements\Source\Unit;
use RuntimeException;
use stdClass;

final class CatalogSource implements SourceExtension
{
    public function __construct(private readonly ResourceLoader $loader = new ResourceLoader())
    {
    }

    /** @return list<Unit> */
    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        $catalog = json_decode($this->loader->read($source, $directory, $live), false, 512, JSON_THROW_ON_ERROR);
        if (!$catalog instanceof stdClass) {
            throw new RuntimeException('The catalog must map stable entry IDs to source text.');
        }
        $units = [];
        foreach (get_object_vars($catalog) as $id => $text) {
            if (!is_string($id) || $id === '' || !is_string($text) || trim($text) === '') {
                throw new RuntimeException('Every catalog entry needs a nonempty ID and text.');
            }
            if ($selector === '*' || $selector === $id) {
                $units[] = new Unit('entry:' . $id, $text);
            }
        }
        return $units;
    }
}
