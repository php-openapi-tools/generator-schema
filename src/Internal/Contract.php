<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Schema\Internal;

use OpenAPITools\Contract\FileGenerator;
use OpenAPITools\Contract\Package;
use OpenAPITools\Generator\Utils\Builder\DocBlockBuilder;
use OpenAPITools\Generator\Utils\Type\DocBlockTag;
use OpenAPITools\Generator\Utils\Type\PropertyTypeResolver;
use OpenAPITools\Representation;
use OpenAPITools\Utils\File;
use OpenAPITools\Utils\Namespace_;
use PhpParser\BuilderFactory;

use function array_key_exists;
use function array_values;
use function count;

final readonly class Contract implements FileGenerator
{
    public function __construct(private BuilderFactory $builderFactory)
    {
    }

    /** @return iterable<File> */
    public function generate(Package $package, Representation\Namespaced\Representation $representation): iterable
    {
        $contracts = [];
        foreach ($representation->schemas as $schema) {
            foreach ($schema->contracts as $contract) {
                $fqcn = $contract->className->fullyQualified->source;
                if (array_key_exists($fqcn, $contracts)) {
                    continue;
                }

                $contracts[$fqcn] = $fqcn;

                yield from $this->generateContract($package->destination->source, $package->namespace, $contract);
            }
        }
    }

    /** @return iterable<File> */
    private function generateContract(string $pathPrefix, Namespace_ $namespace, Representation\Namespaced\Contract $contract): iterable
    {
        $interface          = $this->builderFactory->interface($contract->className->className);
        $contractProperties = [];
        foreach ($contract->properties as $property) {
            $resolved = PropertyTypeResolver::resolve($property, DocBlockTag::Property);
            if ($resolved->docBlockLine === '' || array_key_exists($property->name, $contractProperties)) {
                continue;
            }

            $contractProperties[$property->name] = $resolved->docBlockLine;
        }

        if (count($contractProperties) > 0) {
            $interface->setDocComment(DocBlockBuilder::fromLines(array_values($contractProperties)));
        }

        yield new File($pathPrefix, $contract->className->relative, $this->builderFactory->namespace($contract->className->namespace->source)->addStmt($interface)->getNode(), File::DO_LOAD_ON_WRITE);
    }
}
