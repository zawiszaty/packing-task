<?php

declare(strict_types=1);

namespace Tests\Support\Fake\Presentation\Serializer;

use RuntimeException;
use Symfony\Component\Serializer\SerializerInterface;

final class FailingDeserializeSerializer implements SerializerInterface
{
    public function serialize(mixed $data, string $format, array $context = []): string
    {
        return '{}';
    }

    public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        throw new RuntimeException('broken serializer');
    }
}
