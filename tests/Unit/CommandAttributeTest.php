<?php

declare(strict_types=1);

namespace SuperKernel\Command\Tests\Unit;

use Attribute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use SuperKernel\Command\Attribute\Command;

use function serialize;
use function unserialize;

final class CommandAttributeTest extends TestCase
{
    /** @throws ReflectionException When inspecting the public declaration contract fails */
    public function testDeclaresOnlyTheFourApprovedParametersOnANonRepeatableClassAttribute(): void
    {
        $class = new ReflectionClass(Command::class);
        self::assertTrue($class->isFinal());
        self::assertTrue($class->isReadOnly());
        self::assertSame(Attribute::TARGET_CLASS, $class->getAttributes(Attribute::class)[0]->newInstance()->flags);
        $constructor = $class->getConstructor();
        self::assertSame(1, $constructor->getNumberOfRequiredParameters());
        $parameters = $constructor->getParameters();
        self::assertCount(4, $parameters);
        self::assertSame('name', $parameters[0]->getName());
        self::assertSame('description', $parameters[1]->getName());
        self::assertSame('aliases', $parameters[2]->getName());
        self::assertSame('hidden', $parameters[3]->getName());
        self::assertSame('string', (string) $parameters[0]->getType());
        self::assertSame('?string', (string) $parameters[1]->getType());
        self::assertSame('array', (string) $parameters[2]->getType());
        self::assertSame('bool', (string) $parameters[3]->getType());
        self::assertNull($parameters[1]->getDefaultValue());
        self::assertSame([], $parameters[2]->getDefaultValue());
        self::assertFalse($parameters[3]->getDefaultValue());
    }

    public function testPreservesPassiveValuesAcrossSerialization(): void
    {
        $default = new Command('app:default');
        self::assertSame('app:default', $default->name);
        self::assertNull($default->description);
        self::assertSame([], $default->aliases);
        self::assertFalse($default->hidden);
        $declaration = new Command('app:example', 'Example description.', [
            'example',
        ], true);
        $restored = unserialize(serialize($declaration), [
            'allowed_classes' => [
                Command::class,
            ],
        ]);
        self::assertEquals($declaration, $restored);
    }
}
