<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Schema\Internal\Schema;

use Attribute;
use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\PropertyCaster;
use OpenAPITools\Generator\Utils\Builder\ExpressionBuilder;
use OpenAPITools\Generator\Utils\Builder\StatementBuilder;
use OpenAPITools\Representation\Namespaced\Property;
use OpenAPITools\Representation\Namespaced\Schema;
use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\File;
use PhpParser\BuilderFactory;
use PhpParser\Node\Stmt;

use function count;
use function implode;
use function is_scalar;
use function sort;

final class SingleCastUnionToType
{
    /** @return iterable<File> */
    public static function generate(BuilderFactory $builderFactory, string $pathPrefix, ClassString $classString, Schema ...$schemas): iterable
    {
        $stmt = $builderFactory->namespace($classString->namespace->source);

        $class = $builderFactory->class($classString->className)->makeFinal()->addAttribute(
            $builderFactory->attribute(
                '\\' . Attribute::class,
                [
                    $builderFactory->classConstFetch(
                        '\\' . Attribute::class,
                        'TARGET_PARAMETER',
                    ),
                ],
            ),
        )->implement('\\' . PropertyCaster::class)->addStmt(
            $builderFactory->method('cast')->makePublic()->addParams([
                $builderFactory->param('value')->setType('mixed'),
                $builderFactory->param('hydrator')->setType('\\' . ObjectMapper::class),
            ])->setReturnType('mixed')->addStmts([
                new Stmt\If_(
                    ExpressionBuilder::funcCall('is_array', ['value']),
                    [
                        'stmts' => [
                            StatementBuilder::assign(
                                'signatureChunks',
                                ExpressionBuilder::funcCall('array_unique', [
                                    ExpressionBuilder::funcCall('array_keys', ['value']),
                                ]),
                            ),
                            new Stmt\Expression(
                                ExpressionBuilder::funcCall('sort', ['signatureChunks']),
                            ),
                            StatementBuilder::assign(
                                'signature',
                                ExpressionBuilder::funcCall('implode', [
                                    ExpressionBuilder::literalString('|'),
                                    'signatureChunks',
                                ]),
                            ),
                            ...(static function (BuilderFactory $builderFactory, ClassString $classString, Schema ...$schemas): iterable {
                                foreach ($schemas as $schema) {
                                    $condition = ExpressionBuilder::identical(
                                        ExpressionBuilder::var('signature'),
                                        ExpressionBuilder::literalString(
                                            implode(
                                                '|',
                                                [
                                                    ...(static function (Property ...$properties): iterable {
                                                        $names = [];
                                                        foreach ($properties as $property) {
                                                            $names[] = $property->sourceName;
                                                        }

                                                        sort($names);

                                                        return $names;
                                                    })(...$schema->properties),
                                                ],
                                            ),
                                        ),
                                    );
                                    foreach ($schema->properties as $property) {
                                        $enumConditionals = [];
                                        foreach ($property->enum as $enumPossibility) {
                                            if (! is_scalar($enumPossibility) && $enumPossibility !== null) {
                                                continue;
                                            }

                                            $enumConditionals[] = ExpressionBuilder::identical(
                                                ExpressionBuilder::arrayFetch('value', $property->sourceName),
                                                $builderFactory->val($enumPossibility),
                                            );
                                        }

                                        if (count($enumConditionals) <= 0) {
                                            continue;
                                        }

                                        $condition = ExpressionBuilder::andAll([
                                            $condition,
                                            count($enumConditionals) === 1
                                                ? $enumConditionals[0]
                                                : ExpressionBuilder::orAll($enumConditionals),
                                        ]);
                                    }

                                    yield new Stmt\If_(
                                        $condition,
                                        [
                                            'stmts' => [
                                                StatementBuilder::tryReturnIgnoringThrowable(
                                                    new Stmt\Return_(
                                                        ExpressionBuilder::methodCall(
                                                            ExpressionBuilder::var('hydrator'),
                                                            'hydrateObject',
                                                            [
                                                                ExpressionBuilder::classConstant($classString->fullyQualified->source),
                                                                'value',
                                                            ],
                                                        ),
                                                    ),
                                                ),
                                            ],
                                        ],
                                    );
                                }
                            })($builderFactory, $classString, ...$schemas),
                        ],
                    ],
                ),
                new Stmt\Return_(ExpressionBuilder::var('value')),
            ]),
        );

        yield new File($pathPrefix, $classString->relative, $stmt->addStmt($class)->getNode(), File::DO_LOAD_ON_WRITE);
    }
}
