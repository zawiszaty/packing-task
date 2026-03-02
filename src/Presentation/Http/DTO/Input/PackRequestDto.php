<?php

declare(strict_types=1);

namespace App\Presentation\Http\DTO\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class PackRequestDto
{
    /**
     * @var list<PackProductRequestDto>
     */
    #[Assert\Count(min: 1, minMessage: 'At least one product is required')]
    #[Assert\Valid]
    public array $products = [];
}
