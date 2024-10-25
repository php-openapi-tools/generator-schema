<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Schema\DataTests;

use OpenAPITools\Utils\File;
use WyriHaximus\TestUtilities\TestCase;

final class Basic extends TestCase
{
    public static function assert(File ...$files): void
    {
        self::assertCount(6, $files);

        self::assertArrayHasKey('Contract\Basic', $files);
        self::assertArrayHasKey('Schema\Basic', $files);

        self::assertStringContainsString(' * @property string $id', $files['Contract\Basic']->contents);
        self::assertStringContainsString(' * @property string $name', $files['Contract\Basic']->contents);
        self::assertStringContainsString('interface Basic', $files['Contract\Basic']->contents);

        self::assertStringContainsString('final readonly class Basic implements \ApiClients\Client\GitHub\Contract\Basic', $files['Schema\Basic']->contents);
        self::assertStringContainsString('const SCHEMA_JSON = \'{', $files['Schema\Basic']->contents);
        self::assertStringContainsString('public function __construct(public string $id, public string $name)', $files['Schema\Basic']->contents);
    }
}
