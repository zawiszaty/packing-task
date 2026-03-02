<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class PackProductRequestDto
{
    #[Assert\Positive(message: 'width must be greater than 0')]
    public float $width;

    #[Assert\Positive(message: 'height must be greater than 0')]
    public float $height;

    #[Assert\Positive(message: 'length must be greater than 0')]
    public float $length;

    #[Assert\Positive(message: 'weight must be greater than 0')]
    public float $weight;
}
