<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator\Schema;

use cebe\openapi\Reader;
use OpenAPITools\Configuration\Gathering;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Gatherer\Gatherer;
use OpenAPITools\Generator\Schema\Schema;
use OpenAPITools\Representation\Representation;
use OpenAPITools\TestData\DataSet;
use OpenAPITools\TestData\Provider;
use OpenAPITools\Utils\File;
use OpenAPITools\Utils\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

use function call_user_func;
use function class_exists;
use function is_string;
use function method_exists;

final class SchemaTest extends TestCase
{
    #[Test]
    #[DataProviderExternal(Provider::class, 'sets')]
    public function gather(DataSet $dataSet): void
    {
        $representation = self::loadSpec($dataSet->fileName);

        $testClassName = '\OpenAPITools\Tests\Generator\Schema\DataTests\\' . $dataSet->name;
        self::assertTrue(class_exists($testClassName));
        self::assertTrue(method_exists($testClassName, 'assert'));

        $package = new Package(
            new Package\Metadata(
                'GitHub',
                'Fully type safe generated GitHub REST API client',
                [],
            ),
            'api-clients',
            'github',
            'git@github.com:php-api-clients/github.git',
            'v0.2.x',
            null,
            new Package\Templates(
                __DIR__ . '/templates',
                [],
            ),
            new Package\Destination(
                'github',
                'src',
                'tests',
            ),
            new Namespace_(
                'ApiClients\Client\GitHub',
                'ApiClients\Tests\Client\GitHub',
            ),
            new Package\QA(
                phpcs: new Package\QA\Tool(true, null),
                phpstan: new Package\QA\Tool(
                    true,
                    'etc/phpstan-extension.neon',
                ),
                psalm: new Package\QA\Tool(false, null),
            ),
            new Package\State(
                [
                    'composer.json',
                    'composer.lock',
                ],
            ),
            [],
        );

        $files          = [];
        $generatedFiles = (new Schema(new BuilderFactory()))->generate($package, $representation->namespace($package->namespace));

        foreach ($generatedFiles as $generatedFile) {
            $files[$generatedFile->fqcn] = new File(
                $generatedFile->pathPrefix,
                $generatedFile->fqcn,
                is_string($generatedFile->contents) ? $generatedFile->contents : (new Standard())->prettyPrint([
                    new Node\Stmt\Declare_([
                        new Node\Stmt\DeclareDeclare('strict_types', new Node\Scalar\LNumber(1)),
                    ]),
                    $generatedFile->contents,
                ]),
            );
        }

        // @phpstan-ignore argument.type
        call_user_func($testClassName . '::assert', ...$files); // phpcs:disable
    }

    private static function loadSpec(string $dataSetName): Representation
    {
        return Gatherer::gather(
            Reader::readFromYamlFile($dataSetName),
            new Gathering(
                $dataSetName,
                null,
                new Gathering\Schemas(
                    true,
                    true,
                ),
            ),
        );
    }
}
