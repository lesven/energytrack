<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BatchReadingDTO
{
    #[Assert\NotNull(message: 'Reading date cannot be empty.')]
    #[Assert\Type(type: \DateTimeInterface::class)]
    public ?\DateTimeInterface $readingDate = null;

    /**
     * @var SingleMeterReadingDTO[]
     */
    #[Assert\Valid] // Validate nested DTOs
    public array $readings = [];

    // Optional: Add a constraint to ensure at least one reading is entered
    #[Assert\Callback]
    public function validateAtLeastOneReading(ExecutionContextInterface $context, $payload): void
    {
        $hasValue = false;
        foreach ($this->readings as $reading) {
            if ($reading->value !== null) {
                $hasValue = true;
                break;
            }
        }

        if (!$hasValue) {
            $context->buildViolation('Please enter at least one meter reading.')
                ->atPath('readings') // Associate error with the collection
                ->addViolation();
        }
    }
}
