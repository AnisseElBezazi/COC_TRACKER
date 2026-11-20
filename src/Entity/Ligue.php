<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\LigueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LigueRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['ligue:lire']],
)]
#[UniqueEntity('idSupercell')]
class Ligue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    #[Assert\Positive]
    #[Groups(['ligue:lire'])]
    private ?int $idSupercell = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['ligue:lire', 'joueur:lire', 'groupe:lire', 'resultat:lire'])]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    #[Groups(['ligue:lire'])]
    private ?string $urlIcone = null;

    /**
     * @var Collection<int, Joueur>
     */
    #[ORM\OneToMany(targetEntity: Joueur::class, mappedBy: 'ligue')]
    #[Groups(['ligue:lire'])]
    private Collection $joueurs;

    /**
     * @var Collection<int, GroupeLigue>
     */
    #[ORM\OneToMany(targetEntity: GroupeLigue::class, mappedBy: 'ligue')]
    #[Groups(['ligue:lire'])]
    private Collection $groupes;

    /**
     * @var Collection<int, ResultatSemaine>
     */
    #[ORM\OneToMany(targetEntity: ResultatSemaine::class, mappedBy: 'ligue')]
    #[Groups(['ligue:lire'])]
    private Collection $resultats;

    public function __construct()
    {
        $this->joueurs = new ArrayCollection();
        $this->groupes = new ArrayCollection();
        $this->resultats = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdSupercell(): ?int
    {
        return $this->idSupercell;
    }

    public function setIdSupercell(int $idSupercell): static
    {
        $this->idSupercell = $idSupercell;

        return $this;
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

    public function getUrlIcone(): ?string
    {
        return $this->urlIcone;
    }

    public function setUrlIcone(?string $urlIcone): static
    {
        $this->urlIcone = $urlIcone;

        return $this;
    }

    /**
     * @return Collection<int, Joueur>
     */
    public function getJoueurs(): Collection
    {
        return $this->joueurs;
    }

    public function addJoueur(Joueur $joueur): static
    {
        if (!$this->joueurs->contains($joueur)) {
            $this->joueurs->add($joueur);
            $joueur->setLigue($this);
        }

        return $this;
    }

    public function removeJoueur(Joueur $joueur): static
    {
        if ($this->joueurs->removeElement($joueur)) {
            // set the owning side to null (unless already changed)
            if ($joueur->getLigue() === $this) {
                $joueur->setLigue(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, GroupeLigue>
     */
    public function getGroupes(): Collection
    {
        return $this->groupes;
    }

    public function addGroupe(GroupeLigue $groupe): static
    {
        if (!$this->groupes->contains($groupe)) {
            $this->groupes->add($groupe);
            $groupe->setLigue($this);
        }

        return $this;
    }

    public function removeGroupe(GroupeLigue $groupe): static
    {
        if ($this->groupes->removeElement($groupe)) {
            // set the owning side to null (unless already changed)
            if ($groupe->getLigue() === $this) {
                $groupe->setLigue(null);
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
            $resultat->setLigue($this);
        }

        return $this;
    }

    public function removeResultat(ResultatSemaine $resultat): static
    {
        if ($this->resultats->removeElement($resultat)) {
            // set the owning side to null (unless already changed)
            if ($resultat->getLigue() === $this) {
                $resultat->setLigue(null);
            }
        }

        return $this;
    }
}
