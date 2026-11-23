<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\LigneCompositionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LigneCompositionRepository::class)]
#[ORM\UniqueConstraint(name: 'ligne_composition_troupe', columns: ['composition_id', 'troupe_id'])]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['ligne:lire']],
)]
#[UniqueEntity(fields: ['composition', 'troupe'])]
class LigneComposition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Positive]
    #[Groups(['composition:lire', 'ligne:lire'])]
    private ?int $quantite = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['ligne:lire'])]
    private ?Composition $composition = null;

    #[ORM\ManyToOne(inversedBy: 'lignesComposition')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['composition:lire', 'ligne:lire'])]
    private ?Troupe $troupe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getComposition(): ?Composition
    {
        return $this->composition;
    }

    public function setComposition(?Composition $composition): static
    {
        $this->composition = $composition;

        return $this;
    }

    public function getTroupe(): ?Troupe
    {
        return $this->troupe;
    }

    public function setTroupe(?Troupe $troupe): static
    {
        $this->troupe = $troupe;

        return $this;
    }
}
