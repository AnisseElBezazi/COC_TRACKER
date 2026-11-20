<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\CombatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CombatRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(parameters: [
            'attaquant' => new QueryParameter(filter: new ExactFilter(), property: 'attaquant.tag', description: "Tag exact de l'attaquant, ex. #82U22QLL"),
            'defenseur' => new QueryParameter(filter: new ExactFilter(), property: 'defenseur.tag', description: 'Tag exact du défenseur, ex. #82U22QLL'),
        ]),
    ],
    normalizationContext: ['groups' => ['combat:lire']],
)]
#[UniqueEntity('empreinte')]
class Combat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: ['RANKED', 'LEGEND'])]
    #[Groups(['combat:lire'])]
    private ?string $typeCombat = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 3)]
    #[Groups(['combat:lire'])]
    private ?int $etoiles = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['combat:lire'])]
    private ?int $pourcentage = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(['combat:lire'])]
    private ?int $trophees = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['combat:lire'])]
    private ?\DateTimeImmutable $dateCombat = null;

    #[ORM\Column(length: 40, unique: true)]
    #[Assert\NotBlank]
    private ?string $empreinte = null;

    #[ORM\ManyToOne(inversedBy: 'attaques')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['combat:lire'])]
    private ?Joueur $attaquant = null;

    #[ORM\ManyToOne(inversedBy: 'defenses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['combat:lire'])]
    private ?Joueur $defenseur = null;

    #[ORM\ManyToOne(inversedBy: 'combats')]
    #[Groups(['combat:lire'])]
    private ?GroupeLigue $groupe = null;

    #[ORM\ManyToOne(inversedBy: 'combats')]
    #[Groups(['combat:lire'])]
    private ?Composition $composition = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeCombat(): ?string
    {
        return $this->typeCombat;
    }

    public function setTypeCombat(string $typeCombat): static
    {
        $this->typeCombat = $typeCombat;

        return $this;
    }

    public function getEtoiles(): ?int
    {
        return $this->etoiles;
    }

    public function setEtoiles(int $etoiles): static
    {
        $this->etoiles = $etoiles;

        return $this;
    }

    public function getPourcentage(): ?int
    {
        return $this->pourcentage;
    }

    public function setPourcentage(int $pourcentage): static
    {
        $this->pourcentage = $pourcentage;

        return $this;
    }

    public function getTrophees(): ?int
    {
        return $this->trophees;
    }

    public function setTrophees(?int $trophees): static
    {
        $this->trophees = $trophees;

        return $this;
    }

    public function getDateCombat(): ?\DateTimeImmutable
    {
        return $this->dateCombat;
    }

    public function setDateCombat(?\DateTimeImmutable $dateCombat): static
    {
        $this->dateCombat = $dateCombat;

        return $this;
    }

    public function getEmpreinte(): ?string
    {
        return $this->empreinte;
    }

    public function setEmpreinte(string $empreinte): static
    {
        $this->empreinte = $empreinte;

        return $this;
    }

    public function getAttaquant(): ?Joueur
    {
        return $this->attaquant;
    }

    public function setAttaquant(?Joueur $attaquant): static
    {
        $this->attaquant = $attaquant;

        return $this;
    }

    public function getDefenseur(): ?Joueur
    {
        return $this->defenseur;
    }

    public function setDefenseur(?Joueur $defenseur): static
    {
        $this->defenseur = $defenseur;

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

    public function getComposition(): ?Composition
    {
        return $this->composition;
    }

    public function setComposition(?Composition $composition): static
    {
        $this->composition = $composition;

        return $this;
    }
}
