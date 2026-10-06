<?php

namespace Tests;

use Codewiser\Storage\BucketArgument;
use PHPUnit\Framework\TestCase;

class BucketArgumentTest extends TestCase
{
    public function testItEnumeratesOnlyBackedCases(): void
    {
        $this->assertEquals([Bucket::docs], (new BucketArgument(new Post()))->buckets());

        // An untyped argument, a pure enum and the `\BackedEnum` interface
        // itself declare nothing to enumerate.
        $this->assertEquals([], (new BucketArgument(new PlainPost()))->buckets());
        $this->assertEquals([], (new BucketArgument(new PurePost()))->buckets());
        $this->assertEquals([], (new BucketArgument(new Model()))->buckets());
    }

    public function testItCastsANameToTheDeclaredCase(): void
    {
        $this->assertSame(Bucket::docs, (new BucketArgument(new Post()))->cast('docs'));
    }

    public function testItKeepsANameTheArgumentAccepts(): void
    {
        // `Tests\Model` accepts `null|string|\BackedEnum`, `SoftPost` anything.
        $this->assertSame('docs', (new BucketArgument(new Model()))->cast('docs'));
        $this->assertSame('docs', (new BucketArgument(new SoftPost()))->cast('docs'));
    }

    public function testItRejectsANameTheArgumentCannotTake(): void
    {
        $cases = [
            [new NoParamPost(), 'no bucket argument'],
            [new PurePost(), 'a pure enum without a backing value'],
            [new Post(), 'expected one of: "docs"'],
        ];

        foreach ($cases as [$owner, $hint]) {
            try {
                (new BucketArgument($owner))->cast('missing');

                $this->fail(sprintf('Expected an InvalidArgumentException for %s', $owner::class));
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString($owner::class, $exception->getMessage());
                $this->assertStringContainsString($hint, $exception->getMessage());
            }
        }
    }
}
