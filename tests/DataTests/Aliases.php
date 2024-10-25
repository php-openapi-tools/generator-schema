<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Schema\DataTests;

use OpenAPITools\Utils\File;
use WyriHaximus\TestUtilities\TestCase;

final class Aliases extends TestCase
{
    public static function assert(File ...$files): void
    {
        self::assertCount(19, $files);
    }
}
