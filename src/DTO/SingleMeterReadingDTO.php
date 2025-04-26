<?php

namespace App\DTO;

use App\Entity\Meter;
use Symfony\Component\Validator\Constraints as Assert;

class SingleMeterReadingDTO
{
    public ?Meter $meter = null;

    #[Assert\Type(type: 'float', message: 'The value {{ value }} is not a valid {{ type }}.')]
    #[Assert\PositiveOrZero(message: 'Reading value must be zero or positive.')]
    public ?float $value = null;
}
