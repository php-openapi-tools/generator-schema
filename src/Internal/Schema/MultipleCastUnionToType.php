<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Schema\Internal\Schema;

use Attribute;
use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\PropertyCaster;
use OpenAPITools\Representation\Namespaced\Schema;
use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\File;
use PhpParser\BuilderFactory;
use PhpParser\Node;

final class MultipleCastUnionToType
{
    /** @return iterable<File> */
    public static function generate(BuilderFactory $builderFactory, string $pathPrefix, ClassString $classString, ClassString $wrappingClassString, Schema ...$schemas): iterable
    {
        $stmt = $builderFactory->namespace($classString->namespace->source);

        $class = $builderFactory->class($classString->className)->makeFinal()->makeReadonly()->addAttribute(
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
            $builderFactory->property('wrappedCaster')->makePrivate()->setType($wrappingClassString->fullyQualified->source),
        )->addStmt(
            $builderFactory->method('__construct')->makePublic()->addStmts([
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        $builderFactory->propertyFetch(
                            $builderFactory->var('this'),
                            'wrappedCaster',
                        ),
                        $builderFactory->new($wrappingClassString->fullyQualified->source),
                    ),
                ),
            ]),
        )->addStmt(
            $builderFactory->method('cast')->makePublic()->addParams([
                $builderFactory->param('value')->setType('mixed'),
                $builderFactory->param('hydrator')->setType('\\' . ObjectMapper::class),
            ])->setReturnType('mixed')->addStmts([
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        $builderFactory->var('data'),
                        new Node\Expr\Array_(),
                    ),
                ),
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        $builderFactory->var('values'),
                        $builderFactory->var('value'),
                    ),
                ),
                $builderFactory->funcCall(
                    'unset',
                    [
                        $builderFactory->var('value'),
                    ],
                ),
                new Node\Stmt\Foreach_(
                    $builderFactory->var('values'),
                    $builderFactory->var('value'),
                    [
                        'stmts' => [
                            new Node\Stmt\Expression(
                                new Node\Expr\Assign(
                                    new Node\Expr\ArrayDimFetch(
                                        $builderFactory->var('values'),
                                    ),
                                    $builderFactory->methodCall(
                                        $builderFactory->propertyFetch(
                                            $builderFactory->var('this'),
                                            'wrappedCaster',
                                        ),
                                        'cast',
                                        [
                                            $builderFactory->var('value'),
                                            $builderFactory->var('hydrator'),
                                        ],
                                    ),
                                ),
                            ),
                        ],
                    ],
                ),
                new Node\Stmt\Return_(
                    $builderFactory->var('data'),
                ),
            ]),
        );

        yield new File($pathPrefix, $classString->relative, $stmt->addStmt($class)->getNode());
    }
}
