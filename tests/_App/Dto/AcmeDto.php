<?php

namespace Cesurapp\ApiBundle\Tests\_App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Cesurapp\ApiBundle\AbstractClass\ApiDto;

class AcmeDto extends ApiDto
{
    #[Assert\Length(min: 8)]
    #[Assert\NotNull]
    public ?string $password = null;

    #[Assert\Language]
    #[Assert\NotNull]
    public ?string $language;
}
