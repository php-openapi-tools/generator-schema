<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Schema\DataTests;

use OpenAPITools\Utils\File;
use WyriHaximus\TestUtilities\TestCase;

use function json_encode;
use function str_replace;

use const JSON_PRETTY_PRINT;

final class ExampleData extends TestCase
{
    private const EXAMPLE_DATA = [
        null,
        'generated',
        'generated',
        '999-99-9999',
        '4ccda740-74c3-4cfa-8571-ebf83c8f300a',
        'https://example.com/',
        'hi@example.com',
        '1970-01-01T00:00:00+00:00',
        '127.0.0.1',
        '::1',
        false,
        3,
        0.5,
        1.5,
        [
            'generated',
            'generated',
        ],
        [
            1.6,
            1.7,
        ],
    ];

    public static function assert(File ...$files): void
    {
        self::assertCount(6, $files);

        self::assertArrayHasKey('Contract\Basic', $files);
        self::assertArrayHasKey('Schema\Basic', $files);

        self::assertIsString($files['Schema\Basic']->contents);

        foreach (self::EXAMPLE_DATA as $data) {
            $json = json_encode($data, JSON_PRETTY_PRINT);
            self::assertIsString($json);
            self::assertStringContainsString(str_replace([' ', '\\'], ['', '\\\\'], $json), str_replace(' ', '', $files['Schema\Basic']->contents));
        }
    }
}
