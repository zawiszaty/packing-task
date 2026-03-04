<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class PackProductRequestDto
{
    #[Assert\NotNull(message: 'id is required')]
    #[Assert\Positive(message: 'id must be greater than 0')]
    public ?int $id = null;

    #[Assert\NotNull(message: 'width is required')]
    #[Assert\Positive(message: 'width must be greater than 0')]
    public ?float $width = null;

    #[Assert\NotNull(message: 'height is required')]
    #[Assert\Positive(message: 'height must be greater than 0')]
    public ?float $height = null;

    #[Assert\NotNull(message: 'length is required')]
    #[Assert\Positive(message: 'length must be greater than 0')]
    public ?float $length = null;

    #[Assert\NotNull(message: 'weight is required')]
    #[Assert\Positive(message: 'weight must be greater than 0')]
    public ?float $weight = null;
}
