<?php

namespace App\Entity;

use App\Repository\PointageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PointageRepository::class)]
class Pointage {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $datePointage = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heureEntree = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heureSortie = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heureDebutPause = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heureFinPause = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $utilisateur = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDatePointage(): ?\DateTime {
        return $this->datePointage;
    }

    public function setDatePointage(\DateTime $datePointage): static {
        $this->datePointage = $datePointage;

        return $this;
    }

    public function getHeureEntree(): ?\DateTime {
        return $this->heureEntree;
    }

    public function setHeureEntree(?\DateTime $heureEntree): static {
        $this->heureEntree = $heureEntree;

        return $this;
    }

    public function getHeureSortie(): ?\DateTime {
        return $this->heureSortie;
    }

    public function setHeureSortie(?\DateTime $heureSortie): static {
        $this->heureSortie = $heureSortie;

        return $this;
    }

    public function getHeureDebutPause(): ?\DateTime {
        return $this->heureDebutPause;
    }

    public function setHeureDebutPause(?\DateTime $heureDebutPause): static {
        $this->heureDebutPause = $heureDebutPause;

        return $this;
    }

    public function getHeureFinPause(): ?\DateTime {
        return $this->heureFinPause;
    }

    public function setHeureFinPause(?\DateTime $heureFinPause): static {
        $this->heureFinPause = $heureFinPause;

        return $this;
    }

    public function getUtilisateur(): ?User {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getTotalTravailSeconds(): ?int {
        if (!$this->heureEntree || !$this->heureSortie) {
            return null;
        }

        $entree = $this->heureEntree->getTimestamp();
        $sortie = $this->heureSortie->getTimestamp();

        $total = $sortie - $entree;

        // Soustraction de la pause si elle existe
        if ($this->heureDebutPause && $this->heureFinPause) {
            $pause = $this->heureFinPause->getTimestamp() - $this->heureDebutPause->getTimestamp();

            $total -= $pause;
        }

        return max(0, $total);
    }

    
    public function getTotalTravailFormatted(): ?string {
        $seconds = $this->getTotalTravailSeconds();

        if ($seconds === null) {
            return null;
        }

        return gmdate('H:i:s', $seconds);
    }
}
