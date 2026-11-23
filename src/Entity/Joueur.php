<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\JoueurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JoueurRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(parameters: [
            'tag' => new QueryParameter(filter: new ExactFilter(), property: 'tag', description: 'Tag exact du joueur, ex. #82U22QLL'),
            'hdv' => new QueryParameter(filter: new ExactFilter(), property: 'niveauHdv', description: "Niveau d'HDV exact, ex. 18"),
        ]),
        new Post(),
    ],
    normalizationContext: ['groups' => ['joueur:lire']],
    denormalizationContext: ['groups' => ['joueur:ecrire']],
)]
#[UniqueEntity('tag')]
class Joueur
{
    public const TAG_REGEX = '/^#[0289PYLQGRJCUV]{3,14}$/';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: self::TAG_REGEX)]
    #[Groups(['joueur:lire', 'joueur:ecrire', 'participation:lire', 'combat:lire', 'groupe:lire'])]
    private ?string $tag = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['joueur:lire', 'participation:lire', 'combat:lire', 'groupe:lire'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Positive]
    #[Groups(['joueur:lire'])]
    private ?int $niveauHdv = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[Assert\Regex(pattern: self::TAG_REGEX)]
    #[Groups(['joueur:lire'])]
    private ?string $clanTag = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['joueur:lire'])]
    private ?string $clanNom = null;

    #[ORM\ManyToOne(inversedBy: 'joueurs')]
    #[Groups(['joueur:lire'])]
    private ?Ligue $ligue = null;

    /**
     * @var Collection<int, Participation>
     */
    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'joueur')]
    #[Groups(['joueur:lire'])]
    private Collection $participations;

    /**
     * @var Collection<int, Combat>
     */
    #[ORM\OneToMany(targetEntity: Combat::class, mappedBy: 'attaquant')]
    #[Groups(['joueur:lire'])]
    private Collection $attaques;

    /**
     * @var Collection<int, Combat>
     */
    #[ORM\OneToMany(targetEntity: Combat::class, mappedBy: 'defenseur')]
    #[Groups(['joueur:lire'])]
    private Collection $defenses;

    /**
     * @var Collection<int, ResultatSemaine>
     */
    #[ORM\OneToMany(targetEntity: ResultatSemaine::class, mappedBy: 'joueur')]
    #[Groups(['joueur:lire'])]
    private Collection $resultats;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
        $this->attaques = new ArrayCollection();
        $this->defenses = new ArrayCollection();
        $this->resultats = new ArrayCollection();
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getNiveauHdv(): ?int
    {
        return $this->niveauHdv;
    }

    public function setNiveauHdv(?int $niveauHdv): static
    {
        $this->niveauHdv = $niveauHdv;

        return $this;
    }

    public function getClanTag(): ?string
    {
        return $this->clanTag;
    }

    public function setClanTag(?string $clanTag): static
    {
        $this->clanTag = $clanTag;

        return $this;
    }

    public function getClanNom(): ?string
    {
        return $this->clanNom;
    }

    public function setClanNom(?string $clanNom): static
    {
        $this->clanNom = $clanNom;

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
            $participation->setJoueur($this);
        }

        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            // set the owning side to null (unless already changed)
            if ($participation->getJoueur() === $this) {
                $participation->setJoueur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Combat>
     */
    public function getAttaques(): Collection
    {
        return $this->attaques;
    }

    public function addAttaque(Combat $attaque): static
    {
        if (!$this->attaques->contains($attaque)) {
            $this->attaques->add($attaque);
            $attaque->setAttaquant($this);
        }

        return $this;
    }

    public function removeAttaque(Combat $attaque): static
    {
        if ($this->attaques->removeElement($attaque)) {
            // set the owning side to null (unless already changed)
            if ($attaque->getAttaquant() === $this) {
                $attaque->setAttaquant(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Combat>
     */
    public function getDefenses(): Collection
    {
        return $this->defenses;
    }

    public function addDefense(Combat $defense): static
    {
        if (!$this->defenses->contains($defense)) {
            $this->defenses->add($defense);
            $defense->setDefenseur($this);
        }

        return $this;
    }

    public function removeDefense(Combat $defense): static
    {
        if ($this->defenses->removeElement($defense)) {
            // set the owning side to null (unless already changed)
            if ($defense->getDefenseur() === $this) {
                $defense->setDefenseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ResultatSemaine>
     */
    public function getResultats(): Collection
    {
        return $this->resultats;
    }

    public function addResultat(ResultatSemaine $resultat): static
    {
        if (!$this->resultats->contains($resultat)) {
            $this->resultats->add($resultat);
            $resultat->setJoueur($this);
        }

        return $this;
    }

    public function removeResultat(ResultatSemaine $resultat): static
    {
        if ($this->resultats->removeElement($resultat)) {
            // set the owning side to null (unless already changed)
            if ($resultat->getJoueur() === $this) {
                $resultat->setJoueur(null);
            }
        }

        return $this;
    }
}
