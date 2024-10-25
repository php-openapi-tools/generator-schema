<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Schema;

use OpenAPITools\Contract\FileGenerator;
use OpenAPITools\Contract\Package;
use OpenAPITools\Representation;
use OpenAPITools\Utils\File;
use PhpParser\BuilderFactory;

final readonly class Schema implements FileGenerator
{
    private Internal\Contract $contract;
    private Internal\Error $error;
    private Internal\Schema $schema;

    public function __construct(BuilderFactory $builderFactory)
    {
        $this->contract = new Internal\Contract($builderFactory);
        $this->error    = new Internal\Error($builderFactory);
        $this->schema   = new Internal\Schema($builderFactory);
    }

    /** @return iterable<File> */
    public function generate(Package $package, Representation\Namespaced\Representation $representation): iterable
    {
        yield from $this->contract->generate($package, $representation);
        yield from $this->error->generate($package, $representation);
        yield from $this->schema->generate($package, $representation);
    }
}
