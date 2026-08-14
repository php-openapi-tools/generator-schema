<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Schema\DataTests;

use OpenAPITools\Utils\File;
use WyriHaximus\TestUtilities\TestCase;

final class DoubleUseOfTypes extends TestCase
{
    public static function assert(File ...$files): void
    {
        self::assertCount(6, $files);

        self::assertArrayHasKey('Contract\IssueFieldValue', $files);
        self::assertArrayHasKey('Schema\IssueFieldValue', $files);

        self::assertIsString($files['Contract\IssueFieldValue']->contents);
        self::assertIsString($files['Schema\IssueFieldValue']->contents);

        self::assertStringContainsString(
            '@property string|int|float $value',
            $files['Contract\IssueFieldValue']->contents,
        );

        self::assertStringContainsString(
            'public function __construct(public string|int|float $value)',
            $files['Schema\IssueFieldValue']->contents,
        );
        self::assertStringNotContainsString(
            'string|int|float|int',
            $files['Schema\IssueFieldValue']->contents,
        );
    }
}
