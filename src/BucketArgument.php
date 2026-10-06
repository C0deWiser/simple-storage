<?php

namespace Codewiser\Storage;

/**
 * The bucket argument declared by the owner `storage` method.
 *
 * The interface declares no argument, so every implementation decides for
 * itself what a bucket is: a string, a backed enum, or nothing at all. The
 * download controller and the observer both read that decision from the
 * method signature, and neither of them may guess it.
 */
class BucketArgument
{
    /**
     * The first parameter of the owner `storage` method, or null when the
     * method declares none.
     */
    protected ?\ReflectionParameter $parameter;

    public function __construct(protected Attachmentable $owner)
    {
        try {
            $this->parameter = (new \ReflectionMethod($owner, 'storage'))->getParameters()[0] ?? null;
        } catch (\ReflectionException) {
            $this->parameter = null;
        }
    }

    /**
     * Buckets the owner may hold next to its default storage.
     *
     * Only backed enum cases are returned: `MountPoint` builds the bucket
     * name from the backing value, and a pure enum has none. Generic
     * `\BackedEnum` is an interface, not an enum, so it is skipped too, and an
     * owner whose argument is untyped or missing has nothing to enumerate.
     *
     * @return array<int, \BackedEnum>
     */
    public function buckets(): array
    {
        $buckets = [];

        foreach ($this->types() as $type) {
            if ($type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();

            if (! enum_exists($name) || ! is_subclass_of($name, \BackedEnum::class, true)) {
                continue;
            }

            foreach ((new \ReflectionEnum($name))->getCases() as $case) {
                $buckets[] = $case->getValue();
            }
        }

        return $buckets;
    }

    /**
     * Turn a bucket name into the value the argument accepts.
     *
     * The controller hands over the name as a string, while an implementation
     * may declare a backed enum — `storage(Bucket $bucket = null)` — so that
     * `buckets()` is able to enumerate its cases. The name is converted to
     * the declared enum here: a download either resolves or fails with a
     * readable message instead of a `TypeError`.
     *
     * @throws \InvalidArgumentException when the argument cannot take the name
     */
    public function cast(string $bucket): mixed
    {
        if (is_null($this->parameter)) {
            // PHP drops an extra argument silently, so the bucket would be lost.
            throw new \InvalidArgumentException(sprintf(
                '%s::storage() takes no bucket argument, so the "%s" bucket cannot be resolved. Declare `$bucket = null` in the method signature',
                $this->owner::class,
                $bucket
            ));
        }

        $type = $this->parameter->getType();

        // An untyped argument promises nothing, so the name is passed as it is.
        if (is_null($type)) {
            return $bucket;
        }

        $enums = [];
        $pure = [];
        $accepted = [];

        foreach ($this->types() as $named) {
            if ($named->isBuiltin()) {
                if (in_array($named->getName(), ['string', 'mixed'], true)) {
                    return $bucket;
                }

                $accepted[] = $named->getName();

                continue;
            }

            $name = $named->getName();

            if (enum_exists($name)) {
                if (is_subclass_of($name, \BackedEnum::class, true)) {
                    $enums[] = $name;
                } else {
                    $pure[] = $name;
                }

                continue;
            }

            $accepted[] = $name;
        }

        foreach ($enums as $enum) {
            if ($case = $enum::tryFrom($bucket)) {
                return $case;
            }
        }

        if ($enums !== []) {
            $values = [];

            foreach ($enums as $enum) {
                foreach ($enum::cases() as $case) {
                    $values[] = $case->value;
                }
            }

            throw new \InvalidArgumentException(sprintf(
                'The "%s" bucket is not supported by %s::storage(), expected one of: %s',
                $bucket,
                $this->owner::class,
                implode(', ', array_map(json_encode(...), $values))
            ));
        }

        if ($pure !== []) {
            throw new \InvalidArgumentException(sprintf(
                'The "%s" bucket cannot be passed to %s::storage(), its argument is %s, a pure enum without a backing value',
                $bucket,
                $this->owner::class,
                implode(' or ', $pure)
            ));
        }

        throw new \InvalidArgumentException(sprintf(
            'The "%s" bucket cannot be passed to %s::storage()%s',
            $bucket,
            $this->owner::class,
            $accepted === [] ? '' : ', its argument accepts '.implode(' or ', $accepted)
        ));
    }

    /**
     * Types declared by the argument, as seen through a union.
     *
     * @return array<int, \ReflectionNamedType>
     */
    protected function types(): array
    {
        $type = $this->parameter?->getType();

        if (is_null($type)) {
            return [];
        }

        return array_values(array_filter(
            match (true) {
                $type instanceof \ReflectionUnionType => $type->getTypes(),
                $type instanceof \ReflectionNamedType => [$type],
                default => [],
            },
            fn (\ReflectionType $type) => $type instanceof \ReflectionNamedType
        ));
    }
}
