<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\ParticipationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\UniqueConstraint(name: 'participation_joueur_groupe', columns: ['joueur_id', 'groupe_id'])]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(parameters: [
            'joueur' => new QueryParameter(filter: new ExactFilter(), property: 'joueur.tag', description: 'Tag exact du joueur, ex. #82U22QLL'),
        ]),
    ],
    normalizationContext: ['groups' => ['participation:lire']],
)]
#[UniqueEntity(fields: ['joueur', 'groupe'])]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['participation:lire', 'groupe:lire'])]
    private ?int $trophees = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['participation:lire', 'groupe:lire'])]
    private ?int $attaquesGagnees = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['participation:lire', 'groupe:lire'])]
    private ?int $attaquesPerdues = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['participation:lire', 'groupe:lire'])]
    private ?int $defensesGagnees = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    #[Groups(['participation:lire', 'groupe:lire'])]
    private ?int $defensesPerdues = null;

    /** Nombre d'attaques (gagnées + perdues) au moment où les combats du joueur ont été importés ; null = jamais importés. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $attaquesImportees = null;

    #[ORM\ManyToOne(inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['participation:lire', 'groupe:lire'])]
    private ?Joueur $joueur = null;

    #[ORM\ManyToOne(inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['participation:lire'])]
    private ?GroupeLigue $groupe = null;

    public function getAttaquesImportees(): ?int
    {
        return $this->attaquesImportees;
    }

    public function setAttaquesImportees(?int $attaquesImportees): static
    {
        $this->attaquesImportees = $attaquesImportees;

        return $this;
    }

    /** Vrai si le joueur a attaqué depuis le dernier import de ses combats (ou si ils n'ont jamais été importés). */
    public function aDeNouvellesAttaques(): bool
    {
        return null === $this->attaquesImportees || $this->attaquesImportees < $this->attaquesGagnees + $this->attaquesPerdues;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getJoueur(): ?Joueur
    {
        return $this->joueur;
    }

    public function setJoueur(?Joueur $joueur): static
    {
        $this->joueur = $joueur;

        return $this;
    }

    public function getGroupe(): ?GroupeLigue
    {
        return $this->groupe;
    }

    public function setGroupe(?GroupeLigue $groupe): static
    {
        $this->groupe = $groupe;

        return $this;
    }
}
