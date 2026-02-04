<?php

namespace App\Entity;

use App\Repository\ModificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModificationRepository::class)]
class Modification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $date = null;

    #[ORM\Column(length: 25)]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column]
    private ?int $objetId = null;

    #[ORM\Column(nullable: true)]
    private ?array $anciennesDonnees = null;

    #[ORM\Column(nullable: true)]
    private ?array $nouvellesDonnees = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getObjetId(): ?int
    {
        return $this->objetId;
    }

    public function setObjetId(int $objetId): static
    {
        $this->objetId = $objetId;

        return $this;
    }

    public function getAnciennesDonnees(): ?array
    {
        return $this->anciennesDonnees;
    }

    public function setAnciennesDonnees(?array $anciennesDonnees): static
    {
        $this->anciennesDonnees = $anciennesDonnees;

        return $this;
    }

    public function getNouvellesDonnees(): ?array
    {
        return $this->nouvellesDonnees;
    }

    public function setNouvellesDonnees(?array $nouvellesDonnees): static
    {
        $this->nouvellesDonnees = $nouvellesDonnees;

        return $this;
    }
}
