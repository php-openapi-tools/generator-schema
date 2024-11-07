<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Schema\Internal\Schema;

use Attribute;
use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\PropertyCaster;
use OpenAPITools\Representation\Namespaced\Property;
use OpenAPITools\Representation\Namespaced\Schema;
use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\File;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use Throwable;

use function array_shift;
use function count;
use function implode;
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
                new Node\Stmt\If_(
                    $builderFactory->funcCall(
                        '\is_array',
                        [
                            $builderFactory->var('value'),
                        ],
                    ),
                    [
                        'stmts' => [
                            new Node\Stmt\Expression(
                                new Node\Expr\Assign(
                                    $builderFactory->var('signatureChunks'),
                                    $builderFactory->funcCall(
                                        '\array_unique',
                                        [
                                            $builderFactory->funcCall(
                                                '\array_keys',
                                                [
                                                    $builderFactory->var('value'),
                                                ],
                                            ),
                                        ],
                                    ),
                                ),
                            ),
                            new Node\Stmt\Expression(
                                $builderFactory->funcCall(
                                    '\sort',
                                    [
                                        $builderFactory->var('signatureChunks'),
                                    ],
                                ),
                            ),
                            new Node\Stmt\Expression(
                                new Node\Expr\Assign(
                                    $builderFactory->var('signature'),
                                    $builderFactory->funcCall(
                                        '\implode',
                                        [
                                            '|',
                                            $builderFactory->var('signatureChunks'),
                                        ],
                                    ),
                                ),
                            ),
                            ...(static function (BuilderFactory $builderFactory, ClassString $classString, Schema ...$schemas): iterable {
                                foreach ($schemas as $schema) {
                                    $condition = new Node\Expr\BinaryOp\Identical(
                                        $builderFactory->var('signature'),
                                        new Node\Scalar\String_(
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
                                            $enumConditionals[] = new Node\Expr\BinaryOp\Identical(
                                                new Node\Expr\ArrayDimFetch(
                                                    $builderFactory->var('value'),
                                                    new Node\Scalar\String_($property->sourceName),
                                                ),
                                                $builderFactory->val($enumPossibility), /** @phpstan-ignore-line */
                                            );
                                        }

                                        if (count($enumConditionals) <= 0) {
                                            continue;
                                        }

                                        $enumCondition = array_shift($enumConditionals);
                                        foreach ($enumConditionals as $enumConditional) {
                                            $enumCondition = new Node\Expr\BinaryOp\BooleanOr(
                                                $enumCondition,
                                                $enumConditional,
                                            );
                                        }

                                        $condition = new Node\Expr\BinaryOp\BooleanAnd(
                                            $condition,
                                            $enumCondition,
                                        );
                                    }

                                    yield new Node\Stmt\If_(
                                        $condition,
                                        [
                                            'stmts' => [
                                                new Node\Stmt\TryCatch([
                                                    new Node\Stmt\Return_(
                                                        $builderFactory->methodCall(
                                                            $builderFactory->var('hydrator'),
                                                            'hydrateObject',
                                                            [
                                                                $builderFactory->classConstFetch(
                                                                    $classString->fullyQualified->source,
                                                                    'class',
                                                                ),
                                                                $builderFactory->var('value'),
                                                            ],
                                                        ),
                                                    ),
                                                ], [
                                                    new Node\Stmt\Catch_(
                                                        [new Node\Name('\\' . Throwable::class)],
                                                    ),
                                                ]),
                                            ],
                                        ],
                                    );
                                }
                            })($builderFactory, $classString, ...$schemas),
                        ],
                    ],
                ),
                new Node\Stmt\Return_(
                    $builderFactory->var('value'),
                ),
            ]),
        );

        yield new File($pathPrefix, $classString->relative, $stmt->addStmt($class)->getNode(), File::DO_LOAD_ON_WRITE);
    }
}
