<?php

namespace App\Entity;

use Bundles\PasswordLoginBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserInformationRepository")
 * @ORM\Table(
 * indexes={
 *    @ORM\Index(name="nivol_idx", columns={"nivol"})
 * })
 */
class UserInformation
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Bundles\PasswordLoginBundle\Entity\User", cascade={"all"})
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false, onDelete="cascade")
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $locale;

    /**
     * @ORM\Column(type="string", length=80, nullable=true)
     */
    private $nivol;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Volunteer")
     */
    private $volunteer;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Structure", inversedBy="users")
     * @ORM\OrderBy({"identifier" = "ASC"})
     */
    private $structures;

    public function __construct()
    {
        $this->structures = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     *
     * @return UserInformation
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @param string|null $locale
     *
     * @return UserInformation
     */
    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getNivol(): ?string
    {
        return $this->nivol;
    }

    public function setNivol(?string $nivol): self
    {
        $this->nivol = $nivol;

        return $this;
    }

    public function getVolunteer(): ?Volunteer
    {
        return $this->volunteer;
    }

    public function setVolunteer(?Volunteer $volunteer): self
    {
        $this->volunteer = $volunteer;

        return $this;
    }

    /**
     * @return Collection|Structure[]
     */
    public function getStructures(): Collection
    {
        return $this->structures;
    }

    public function addStructure(Structure $structure): self
    {
        if (!$this->structures->contains($structure)) {
            $this->structures[] = $structure;
        }

        return $this;
    }

    public function removeStructure(Structure $structure): self
    {
        if ($this->structures->contains($structure)) {
            $this->structures->removeElement($structure);
        }

        return $this;
    }

    /**
     * @param Structure[] $structures
     */
    public function updateStructures(array $structures)
    {
        foreach ($structures as $structure) {
            $this->addStructure($structure);
        }
    }

    /**
     * @return array|ArrayCollection
     */
    public function computeStructureList()
    {
        if (count($this->structures) < 2) {
            return $this->structures;
        }

        return array_filter(
            $this->getStructures()->toArray(), function(Structure $structure) {
            if (0 == $structure->getIdentifier()) {
                return false;
            }

            return true;
        });
    }
}
