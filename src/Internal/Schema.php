<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Schema\Internal;

use EventSauce\ObjectHydrator\MapFrom;
use EventSauce\ObjectHydrator\PropertyCasters\CastListToType;
use OpenAPITools\Contract\FileGenerator;
use OpenAPITools\Contract\Package;
use OpenAPITools\Generator\Schema\Internal\Schema\MultipleCastUnionToType;
use OpenAPITools\Generator\Schema\Internal\Schema\SingleCastUnionToType;
use OpenAPITools\Generator\Utils\Builder\DocBlockBuilder;
use OpenAPITools\Generator\Utils\Type\DocBlockTag;
use OpenAPITools\Generator\Utils\Type\PropertyTypeResolver;
use OpenAPITools\Generator\Utils\Type\UnionTypeUtils;
use OpenAPITools\Representation;
use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\File;
use OpenAPITools\Utils\Namespace_;
use OpenAPITools\Utils\Utils;
use PhpParser\BuilderFactory;
use RuntimeException;

use function count;
use function implode;
use function is_array;
use function is_string;
use function json_encode;
use function md5;
use function str_split;
use function strtoupper;

use const JSON_PRETTY_PRINT;

final readonly class Schema implements FileGenerator
{
    public function __construct(private BuilderFactory $builderFactory)
    {
    }

    /** @return iterable<File> */
    public function generate(Package $package, Representation\Namespaced\Representation $representation): iterable
    {
        foreach ($representation->schemas as $schema) {
            yield from $this->generateSchema($package->destination->source, $package->namespace, $schema);
        }
    }

    /** @return iterable<File> */
    private function generateSchema(string $pathPrefix, Namespace_ $namespace, Representation\Namespaced\Schema $schema): iterable
    {
        $aliases = [];

        foreach ($schema->alias as $alias) {
            $aliases[] = ClassString::factory(
                $namespace,
                $alias,
            );
        }

        $className = $schema->className;
        if (count($aliases) > 0) {
            $json = json_encode($schema->schema->getSerializableData());
            if ($json === false) {
                throw new RuntimeException('Could not encode JSON.');
            }

            $className = ClassString::factory(
                $className->baseNamespace,
                'Schema\\AliasAbstract\\T' . implode('\\T', str_split(strtoupper(md5($json)), 8)),
            );
            $aliases[] = $schema->className;
        }

        $class = $this->builderFactory->class($className->className)->makeReadonly()->implement(...(static function (Representation\Namespaced\Contract ...$contracts): iterable {
            foreach ($contracts as $contract) {
                yield $contract->className->fullyQualified->source;
            }
        })(...$schema->contracts));

        if (count($aliases) === 0) {
            $class = $class->makeFinal();
        } else {
            $class = $class->makeAbstract();
        }

        $class->addStmt(
            $this->builderFactory->classConst(
                'SCHEMA_JSON',
                json_encode($schema->schema->getSerializableData(), JSON_PRETTY_PRINT),
            ),
        )->addStmt(
            $this->builderFactory->classConst(
                'SCHEMA_TITLE',
                $schema->title,
            )->makePublic(),
        )->addStmt(
            $this->builderFactory->classConst(
                'SCHEMA_DESCRIPTION',
                $schema->description,
            )->makePublic(),
        )->addStmt(
            $this->builderFactory->classConst(
                'SCHEMA_EXAMPLE_DATA',
                json_encode($schema->example, JSON_PRETTY_PRINT),
            ),
        );

        $constructor       = $this->builderFactory->method('__construct')->makePublic();
        $constructDocBlock = [];
        foreach ($schema->properties as $property) {
            if ($property->description !== '') {
                $constructDocBlock[] = $property->name . ': ' . $property->description;
            }

            $constructorParam = $this->builderFactory->param($property->name)->makePublic();
            if ($property->name !== $property->sourceName) {
                $constructorParam->addAttribute($this->builderFactory->attribute(
                    '\\' . MapFrom::class,
                    [
                        $property->sourceName,
                    ],
                ));
            }

            if ($property->type->type === 'union' && is_array($property->type->payload)) {
                $schemaClasses = [...UnionTypeUtils::getUnionTypeSchemas($property->type)];

                if (count($schemaClasses) > 0) {
                    $castToUnionToType = ClassString::factory($className->baseNamespace, Utils::className('Internal\\Attribute\\CastUnionToType\\Single\\' . $className->relative . '\\' . $property->name));

                    yield from SingleCastUnionToType::generate($this->builderFactory, $pathPrefix, $castToUnionToType, ...$schemaClasses);

                    $constructorParam->addAttribute($this->builderFactory->attribute($castToUnionToType->fullyQualified->source));
                }
            }

            if ($property->type->type === 'array' && ! is_string($property->type->payload)) {
                if ($property->type->payload instanceof Representation\Namespaced\Property\Type) {
                    if (! $property->type->payload->payload instanceof Representation\Namespaced\Property\Type) {
                        $iterableTypeNode      = $property->type->payload;
                        $remainingIterableType = $iterableTypeNode->payload instanceof Representation\Namespaced\Schema ? null : $iterableTypeNode;

                        if ($remainingIterableType instanceof Representation\Namespaced\Property\Type && (($remainingIterableType->payload instanceof Representation\Namespaced\Property\Type && $remainingIterableType->payload->type === 'union') || is_array($remainingIterableType->payload))) {
                            $schemaClasses = [...UnionTypeUtils::getUnionTypeSchemas($remainingIterableType)];

                            if (count($schemaClasses) > 0) {
                                $castToUnionToType = ClassString::factory($className->baseNamespace, Utils::className('Internal\\Attribute\\CastUnionToType\\Single\\' . $className->relative . '\\' . $property->name));

                                yield from SingleCastUnionToType::generate($this->builderFactory, $pathPrefix, $castToUnionToType, ...$schemaClasses);

                                $constructorParam->addAttribute($this->builderFactory->attribute($castToUnionToType->fullyQualified->source));
                            }
                        }
                    }

                    if ($property->type->payload->payload instanceof Representation\Namespaced\Schema) {
                        $constructorParam->addAttribute($this->builderFactory->attribute(
                            '\\' . CastListToType::class,
                            [
                                $this->builderFactory->classConstFetch(
                                    $property->type->payload->payload->className->fullyQualified->source,
                                    'class',
                                ),
                            ],
                        ));
                    }
                } elseif (is_array($property->type->payload)) {
                    $schemaClasses = [];
                    foreach ($property->type->payload as $payloadType) {
                        $schemaClasses = [...$schemaClasses, ...UnionTypeUtils::getUnionTypeSchemas($payloadType)];
                    }

                    if (count($schemaClasses) > 0) {
                        $castToUnionToType      = ClassString::factory($className->baseNamespace, Utils::className('Internal\\Attribute\\CastUnionToType\\Single\\' . $className->relative . '\\' . $property->name));
                        $arrayCastToUnionToType = ClassString::factory($className->baseNamespace, Utils::className('Internal\\Attribute\\CastUnionToType\\Multiple\\' . $className->relative . '\\' . $property->name));

                        yield from SingleCastUnionToType::generate($this->builderFactory, $pathPrefix, $castToUnionToType, ...$schemaClasses);
                        yield from MultipleCastUnionToType::generate($this->builderFactory, $pathPrefix, $arrayCastToUnionToType, $castToUnionToType, ...$schemaClasses);

                        $constructorParam->addAttribute($this->builderFactory->attribute($arrayCastToUnionToType->fullyQualified->source));
                    }
                }
            }

            $resolved = PropertyTypeResolver::resolve($property, DocBlockTag::Param);
            if ($resolved->docBlockLine !== '') {
                $constructDocBlock[] = $resolved->docBlockLine;
            }

            if ($resolved->typeHint() !== '') {
                $constructorParam->setType($resolved->typeHint());
            }

            $constructor->addParam($constructorParam);
        }

        if (count($constructDocBlock) > 0) {
            $constructor->setDocComment(DocBlockBuilder::fromDocLines($constructDocBlock));
        }

        $class->addStmt($constructor);

        yield new File($pathPrefix, $className->relative, $this->builderFactory->namespace($className->namespace->source)->addStmt($class)->getNode(), File::DO_LOAD_ON_WRITE);

        foreach ($aliases as $alias) {
            $aliasTms   = $this->builderFactory->namespace($alias->namespace->source);
            $aliasClass = $this->builderFactory->class($alias->className)->makeFinal()->makeReadonly()->extend($className->fullyQualified->source);

            yield new File($pathPrefix, $alias->relative, $aliasTms->addStmt($aliasClass)->getNode(), File::DO_LOAD_ON_WRITE);
        }
    }
}
