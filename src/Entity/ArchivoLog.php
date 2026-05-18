<?php

namespace App\Entity;

use App\Repository\ArchivoLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArchivoLogRepository::class)]
class ArchivoLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $empresa = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $usuario = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $archivoOriginal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $archivoBackup = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accion = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpresa(): ?string
    {
        return $this->empresa;
    }

    public function setEmpresa(?string $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getUsuario(): ?string
    {
        return $this->usuario;
    }

    public function setUsuario(?string $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getArchivoOriginal(): ?string
    {
        return $this->archivoOriginal;
    }

    public function setArchivoOriginal(?string $archivoOriginal): static
    {
        $this->archivoOriginal = $archivoOriginal;

        return $this;
    }

    public function getArchivoBackup(): ?string
    {
        return $this->archivoBackup;
    }

    public function setArchivoBackup(?string $archivoBackup): static
    {
        $this->archivoBackup = $archivoBackup;

        return $this;
    }

    public function getAccion(): ?string
    {
        return $this->accion;
    }

    public function setAccion(?string $accion): static
    {
        $this->accion = $accion;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
