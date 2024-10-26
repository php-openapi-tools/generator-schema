<?php

declare(strict_types=1);

namespace OpenAPITools\Generator\Schema\Internal;

use OpenAPITools\Representation;

use function array_filter;
use function array_unique;
use function gettype;
use function implode;
use function is_array;
use function is_string;
use function strlen;
use function trim;

final class UnionTypeUtils
{
    public static function buildUnionType(Representation\Namespaced\Property\Type $type): string
    {
        $typeList = [];
        if (is_array($type->payload)) {
            foreach ($type->payload as $typeInUnion) {
                $typeList[] = match (gettype($typeInUnion->payload)) {
                    'string' => $typeInUnion->payload,
                    'array' => 'array',
                    'object' => match ($typeInUnion->payload::class) {
                        Representation\Namespaced\Schema::class => $typeInUnion->payload->className->relative,
                        Representation\Namespaced\Property\Type::class => self::buildUnionType($typeInUnion->payload),
                    },
                };
            }
        } else {
            $typeList[] = $type->payload;
        }

        return implode(
            '|',
            array_unique(
                array_filter(
                    array_filter(
                        $typeList,
                        static fn ($item): bool => is_string($item),
                    ),
                    static fn (string $item): bool => strlen(trim($item)) > 0,
                ),
            ),
        );
    }

    /** @return iterable<Representation\Namespaced\Schema> */
    public static function getUnionTypeSchemas(Representation\Namespaced\Property\Type $type): iterable
    {
        if (! is_array($type->payload)) {
            return;
        }

        foreach ($type->payload as $typeInUnion) {
            if ($typeInUnion->payload instanceof Representation\Namespaced\Schema) {
                yield $typeInUnion->payload;
            }

            if (! ($typeInUnion->payload instanceof Representation\Namespaced\Property\Type)) {
                continue;
            }

            yield from self::getUnionTypeSchemas($typeInUnion->payload);
        }
    }
}
