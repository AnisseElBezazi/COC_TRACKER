<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TroupeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TroupeRepository::class)]
#[ORM\UniqueConstraint(name: 'troupe_type_id_jeu', columns: ['type', 'id_jeu'])]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['troupe:lire']],
)]
#[UniqueEntity('nom')]
#[UniqueEntity(fields: ['type', 'idJeu'])]
class Troupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['troupe:lire', 'composition:lire'])]
    private ?string $nom = null;

    #[ORM\Column(length: 15)]
    #[Assert\Choice(choices: ['troupe', 'familier', 'heros', 'sort', 'equipement'])]
    #[Groups(['troupe:lire'])]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $idJeu = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Positive]
    #[Groups(['troupe:lire'])]
    private ?int $place = null;

    /**
     * @var Collection<int, Composition>
     */
    #[ORM\ManyToMany(targetEntity: Composition::class, mappedBy: 'heros')]
    private Collection $compositions;

    /**
     * @var Collection<int, LigneComposition>
     */
    #[ORM\OneToMany(targetEntity: LigneComposition::class, mappedBy: 'troupe')]
    #[Groups(['troupe:lire'])]
    private Collection $lignesComposition;

    public function __construct()
    {
        $this->compositions = new ArrayCollection();
        $this->lignesComposition = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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

    public function getIdJeu(): ?int
    {
        return $this->idJeu;
    }

    public function setIdJeu(?int $idJeu): static
    {
        $this->idJeu = $idJeu;

        return $this;
    }

    public function getPlace(): ?int
    {
        return $this->place;
    }

    public function setPlace(?int $place): static
    {
        $this->place = $place;

        return $this;
    }

    /**
     * @return Collection<int, Composition>
     */
    public function getCompositions(): Collection
    {
        return $this->compositions;
    }

    public function addComposition(Composition $composition): static
    {
        if (!$this->compositions->contains($composition)) {
            $this->compositions->add($composition);
            $composition->addHero($this);
        }

        return $this;
    }

    public function removeComposition(Composition $composition): static
    {
        if ($this->compositions->removeElement($composition)) {
            $composition->removeHero($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, LigneComposition>
     */
    public function getLignesComposition(): Collection
    {
        return $this->lignesComposition;
    }

    public function addLignesComposition(LigneComposition $lignesComposition): static
    {
        if (!$this->lignesComposition->contains($lignesComposition)) {
            $this->lignesComposition->add($lignesComposition);
            $lignesComposition->setTroupe($this);
        }

        return $this;
    }

    public function removeLignesComposition(LigneComposition $lignesComposition): static
    {
        if ($this->lignesComposition->removeElement($lignesComposition)) {
            // set the owning side to null (unless already changed)
            if ($lignesComposition->getTroupe() === $this) {
                $lignesComposition->setTroupe(null);
            }
        }

        return $this;
    }
}
