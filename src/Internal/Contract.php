<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Schema\Internal;

use OpenAPITools\Contract\FileGenerator;
use OpenAPITools\Contract\Package;
use OpenAPITools\Representation;
use OpenAPITools\Utils\File;
use OpenAPITools\Utils\Namespace_;
use PhpParser\BuilderFactory;
use RuntimeException;

use function array_key_exists;
use function array_unique;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_string;

use const PHP_EOL;

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
            $types = [];
            if ($property->type->type === 'union' && is_array($property->type->payload)) {
                $types[] = UnionTypeUtils::buildUnionType($property->type);
            }

            if ($property->type->type === 'array' && ! is_string($property->type->payload)) {
                if ($property->type->payload instanceof Representation\Namespaced\Property\Type) {
                    if (! $property->type->payload->payload instanceof Representation\Namespaced\Property\Type) {
                        $iterableType = $property->type->payload;
                        if ($iterableType->payload instanceof Representation\Namespaced\Schema) {
                            $iterableType = $iterableType->payload->className->fullyQualified->source;
                        }

                        if ($iterableType instanceof Representation\Namespaced\Property\Type && (($iterableType->payload instanceof Representation\Namespaced\Property\Type && $iterableType->payload->type === 'union') || is_array($iterableType->payload))) {
                            $iterableType = UnionTypeUtils::buildUnionType($iterableType);
                        }

                        if ($iterableType instanceof Representation\Namespaced\Property\Type) {
                            $iterableType = $iterableType->payload;
                        }

                        if (! is_string($iterableType)) {
                            throw new RuntimeException('At this point $iterableType should be a string');
                        }

                        $compiledTYpe                        = ($property->nullable ? '?' : '') . 'array<' . $iterableType . '>';
                        $contractProperties[$property->name] = '@property ' . $compiledTYpe . ' $' . $property->name;
                    }
                } elseif (is_array($property->type->payload)) {
                    $schemaClasses = [];
                    foreach ($property->type->payload as $payloadType) {
                        $schemaClasses = [...$schemaClasses, ...UnionTypeUtils::getUnionTypeSchemas($payloadType)];
                    }

                    if (count($schemaClasses) > 0) {
                        $compiledTYpe                        = ($property->nullable ? '?' : '') . 'array<' . implode('|', array_unique([
                            ...(static function (Representation\Namespaced\Schema ...$schemas): iterable {
                                foreach ($schemas as $schema) {
                                    yield $schema->className->fullyQualified->source;
                                }
                            })(...$schemaClasses),
                        ])) . '>';
                        $contractProperties[$property->name] = '@property ' . $compiledTYpe . ' $' . $property->name;
                    }
                }

                $types[] = 'array';
            } elseif ($property->type->payload instanceof Representation\Namespaced\Schema) {
                $types[] = $property->type->payload->className->fullyQualified->source;
            } elseif (is_string($property->type->payload)) {
                $types[] = $property->type->payload;
            }

            $types = array_unique($types);

            $nullable = '';
            if ($property->nullable) {
                $nullable = count($types) > 1 || count(explode('|', implode('|', $types))) > 1 ? 'null|' : '?';
            }

            if (count($types) > 0) {
                if (! array_key_exists($property->name, $contractProperties)) {
                    $contractProperties[$property->name] = '@property ' . $nullable . implode('|', $types) . ' $' . $property->name;
                }
            } else {
                if (! array_key_exists($property->name, $contractProperties)) {
                    $contractProperties[$property->name] = '@property $' . $property->name;
                }
            }
        }

        if (count($contractProperties) > 0) {
            $interface->setDocComment('/**' . PHP_EOL . ' * ' . implode(PHP_EOL . ' * ', $contractProperties) . PHP_EOL . ' */');
        }

        yield new File($pathPrefix, $contract->className->relative, $this->builderFactory->namespace($contract->className->namespace->source)->addStmt($interface)->getNode(), File::DO_LOAD_ON_WRITE);
    }
}
