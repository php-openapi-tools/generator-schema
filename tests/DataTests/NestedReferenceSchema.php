<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Schema\DataTests;

use OpenAPITools\Utils\File;
use WyriHaximus\TestUtilities\TestCase;

final class NestedReferenceSchema extends TestCase
{
    public static function assert(File ...$files): void
    {
        self::assertCount(12, $files);
    }
}
