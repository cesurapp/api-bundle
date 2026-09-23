<?php

namespace Cesurapp\ApiBundle\Tests\_App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'tags')]
#[ORM\Entity]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tags')]
        #[ORM\JoinColumn(nullable: false)]
        private User $user,
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $user->getTags()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
