<?php

declare(strict_types=1);

namespace SqlSemantics;

/**
 * A database-specific context of declarations and name resolution settings.
 *
 * @visibility public
 * @example Marking a database-specific declaration context
 *     $context = new class implements \SqlSemantics\AnalysisContext {};
 *     $context instanceof \SqlSemantics\AnalysisContext // => true
 */
interface AnalysisContext
{
}
