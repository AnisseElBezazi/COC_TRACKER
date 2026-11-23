<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\GroupeLigueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupeLigueRepository::class)]
#[ORM\UniqueConstraint(name: 'groupe_ligue_tag_saison', columns: ['tag', 'saison_id'])]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(parameters: [
            'tag' => new QueryParameter(filter: new ExactFilter(), property: 'tag', description: 'Tag exact du groupe, ex. #98G0JLU'),
        ]),
    ],
    normalizationContext: ['groups' => ['groupe:lire']],
)]
#[UniqueEntity(fields: ['tag', 'saisonId'])]
class GroupeLigue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: Joueur::TAG_REGEX)]
    #[Groups(['groupe:lire'])]
    private ?string $tag = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['groupe:lire'])]
    private ?int $saisonId = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Groups(['groupe:lire'])]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Groups(['groupe:lire'])]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\ManyToOne(inversedBy: 'groupes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['groupe:lire'])]
    private ?Ligue $ligue = null;

    /**
     * @var Collection<int, Participation>
     */
    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'groupe')]
    #[Groups(['groupe:lire'])]
    private Collection $participations;

    /**
     * @var Collection<int, Combat>
     */
    #[ORM\OneToMany(targetEntity: Combat::class, mappedBy: 'groupe')]
    #[Groups(['groupe:lire'])]
    private Collection $combats;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
        $this->combats = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function getSaisonId(): ?int
    {
        return $this->saisonId;
    }

    public function setSaisonId(int $saisonId): static
    {
        $this->saisonId = $saisonId;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getLigue(): ?Ligue
    {
        return $this->ligue;
    }

    public function setLigue(?Ligue $ligue): static
    {
        $this->ligue = $ligue;

        return $this;
    }

    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setGroupe($this);
        }

        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            // set the owning side to null (unless already changed)
            if ($participation->getGroupe() === $this) {
                $participation->setGroupe(null);
            }
        }

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
            $combat->setGroupe($this);
        }

        return $this;
    }

    public function removeCombat(Combat $combat): static
    {
        if ($this->combats->removeElement($combat)) {
            // set the owning side to null (unless already changed)
            if ($combat->getGroupe() === $this) {
                $combat->setGroupe(null);
            }
        }

        return $this;
    }
}
