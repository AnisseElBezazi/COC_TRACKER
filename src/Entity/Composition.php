<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\CompositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompositionRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Patch(),
    ],
    normalizationContext: ['groups' => ['composition:lire']],
    denormalizationContext: ['groups' => ['composition:ecrire']],
)]
#[UniqueEntity('code')]
class Composition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['composition:lire', 'combat:lire'])]
    private ?string $code = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    #[Groups(['composition:lire', 'composition:ecrire', 'combat:lire'])]
    private ?string $archetypeTroupes = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Groups(['composition:lire', 'composition:ecrire', 'combat:lire'])]
    private ?string $archetypeHeros = null;

    /**
     * @var Collection<int, Troupe>
     */
    #[ORM\ManyToMany(targetEntity: Troupe::class, inversedBy: 'compositions')]
    #[Groups(['composition:lire'])]
    private Collection $heros;

    /**
     * @var Collection<int, Combat>
     */
    #[ORM\OneToMany(targetEntity: Combat::class, mappedBy: 'composition')]
    #[Groups(['composition:lire'])]
    private Collection $combats;

    /**
     * @var Collection<int, LigneComposition>
     */
    #[ORM\OneToMany(targetEntity: LigneComposition::class, mappedBy: 'composition')]
    #[Groups(['composition:lire'])]
    private Collection $lignes;

    public function __construct()
    {
        $this->heros = new ArrayCollection();
        $this->combats = new ArrayCollection();
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getArchetypeTroupes(): ?string
    {
        return $this->archetypeTroupes;
    }

    public function setArchetypeTroupes(?string $archetypeTroupes): static
    {
        $this->archetypeTroupes = $archetypeTroupes;

        return $this;
    }

    public function getArchetypeHeros(): ?string
    {
        return $this->archetypeHeros;
    }

    public function setArchetypeHeros(?string $archetypeHeros): static
    {
        $this->archetypeHeros = $archetypeHeros;

        return $this;
    }

    /**
     * @return Collection<int, Troupe>
     */
    public function getHeros(): Collection
    {
        return $this->heros;
    }

    public function addHero(Troupe $hero): static
    {
        if (!$this->heros->contains($hero)) {
            $this->heros->add($hero);
        }

        return $this;
    }

    public function removeHero(Troupe $hero): static
    {
        $this->heros->removeElement($hero);

        return $this;
    }

    /**
     * @return Collection<int, Combat>
     */
    public function getCombats(): Collection
    {
        return $this->combats;
    }

    public function addCombat(Combat $combat): static
    {
        if (!$this->combats->contains($combat)) {
            $this->combats->add($combat);
            $combat->setComposition($this);
        }

        return $this;
    }

    public function removeCombat(Combat $combat): static
    {
        if ($this->combats->removeElement($combat)) {
            // set the owning side to null (unless already changed)
            if ($combat->getComposition() === $this) {
                $combat->setComposition(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LigneComposition>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneComposition $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setComposition($this);
        }

        return $this;
    }

    public function removeLigne(LigneComposition $ligne): static
    {
        if ($this->lignes->removeElement($ligne)) {
            // set the owning side to null (unless already changed)
            if ($ligne->getComposition() === $this) {
                $ligne->setComposition(null);
            }
        }

        return $this;
    }
}
