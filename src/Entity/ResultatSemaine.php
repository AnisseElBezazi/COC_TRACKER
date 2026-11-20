<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\ResultatSemaineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ResultatSemaineRepository::class)]
#[ORM\UniqueConstraint(name: 'resultat_joueur_saison', columns: ['joueur_id', 'saison_id'])]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['resultat:lire']],
)]
#[UniqueEntity(fields: ['joueur', 'saisonId'])]
class ResultatSemaine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $saisonId = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Positive]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $classement = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $trophees = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $attaquesGagnees = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $attaquesPerdues = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $etoilesAttaque = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $defensesGagnees = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $defensesPerdues = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $etoilesDefense = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Positive]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?int $combatsMax = null;

    #[ORM\ManyToOne(inversedBy: 'resultats')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['resultat:lire'])]
    private ?Joueur $joueur = null;

    #[ORM\ManyToOne(inversedBy: 'resultats')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['resultat:lire', 'joueur:lire'])]
    private ?Ligue $ligue = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getClassement(): ?int
    {
        return $this->classement;
    }

    public function setClassement(int $classement): static
    {
        $this->classement = $classement;

        return $this;
    }

    public function getTrophees(): ?int
    {
        return $this->trophees;
    }

    public function setTrophees(int $trophees): static
    {
        $this->trophees = $trophees;

        return $this;
    }

    public function getAttaquesGagnees(): ?int
    {
        return $this->attaquesGagnees;
    }

    public function setAttaquesGagnees(int $attaquesGagnees): static
    {
        $this->attaquesGagnees = $attaquesGagnees;

        return $this;
    }

    public function getAttaquesPerdues(): ?int
    {
        return $this->attaquesPerdues;
    }

    public function setAttaquesPerdues(int $attaquesPerdues): static
    {
        $this->attaquesPerdues = $attaquesPerdues;

        return $this;
    }

    public function getEtoilesAttaque(): ?int
    {
        return $this->etoilesAttaque;
    }

    public function setEtoilesAttaque(int $etoilesAttaque): static
    {
        $this->etoilesAttaque = $etoilesAttaque;

        return $this;
    }

    public function getDefensesGagnees(): ?int
    {
        return $this->defensesGagnees;
    }

    public function setDefensesGagnees(int $defensesGagnees): static
    {
        $this->defensesGagnees = $defensesGagnees;

        return $this;
    }

    public function getDefensesPerdues(): ?int
    {
        return $this->defensesPerdues;
    }

    public function setDefensesPerdues(int $defensesPerdues): static
    {
        $this->defensesPerdues = $defensesPerdues;

        return $this;
    }

    public function getEtoilesDefense(): ?int
    {
        return $this->etoilesDefense;
    }

    public function setEtoilesDefense(int $etoilesDefense): static
    {
        $this->etoilesDefense = $etoilesDefense;

        return $this;
    }

    public function getCombatsMax(): ?int
    {
        return $this->combatsMax;
    }

    public function setCombatsMax(int $combatsMax): static
    {
        $this->combatsMax = $combatsMax;

        return $this;
    }

    public function getJoueur(): ?Joueur
    {
        return $this->joueur;
    }

    public function setJoueur(?Joueur $joueur): static
    {
        $this->joueur = $joueur;

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
}
