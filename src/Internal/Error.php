<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Schema\Internal;

use OpenAPITools\Contract\Package;
use OpenAPITools\Representation;
use OpenAPITools\Utils\File;
use PhpParser\BuilderFactory;

final class Error
{
    public function __construct(private BuilderFactory $builderFactory)
    {
    }

    /** @return iterable<File> */
    public function generate(Package $package, Representation\Namespaced\Representation $representation): iterable
    {
        foreach ($representation->schemas as $schema) {
            yield from $this->generateError($package->destination->source, $schema);
        }
    }

    /** @return iterable<File> */
    private function generateError(string $pathPrefix, Representation\Namespaced\Schema $schema): iterable
    {
        $stmt = $this->builderFactory->namespace($schema->errorClassName->namespace->source);

        $class = $this->builderFactory->class($schema->errorClassName->className)->extend('\\' . \Error::class)->makeFinal();

        $class->addStmt((new BuilderFactory())->method('__construct')->makePublic()->addParam(
            $this->builderFactory->param('status')->setType('int')->makePublic(),
        )->addParam(
            $this->builderFactory->param('error')->setType($schema->className->fullyQualified->source)->makePublic(),
        ));

        yield new File($pathPrefix, $schema->errorClassName->relative, $stmt->addStmt($class)->getNode(), File::DO_LOAD_ON_WRITE);
    }
}
