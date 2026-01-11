<?php

namespace App\Entity;

use ACSEO\TypesenseBundle\Attribute\TypesenseDocument;
use ACSEO\TypesenseBundle\Attribute\TypesenseField;
use App\Repository\MovieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovieRepository::class)]
#[ORM\Table(name: 'movies')]
#[ORM\Index(columns: ['title'], name: 'idx_title')]
#[TypesenseDocument(name: 'movies')]
class Movie
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[TypesenseField]
    private ?string $id = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[TypesenseField]
    private ?int $tmdb_id = null;

    #[ORM\Column(length: 255)]
    #[TypesenseField]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[TypesenseField]
    private ?string $overview = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[TypesenseField(type: 'string[]', facet: true)]
    private ?array $genres = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[TypesenseField]
    private ?string $poster = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[TypesenseField(facet: true)]
    private ?int $release_date = null;

    #[ORM\Column(type: 'vector', length: 768, nullable: true)]
    #[TypesenseField(type: 'float[]', embed: ['fields' => ['title', 'overview']])]
    private ?string $embedding = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTmdbId(): ?int
    {
        return $this->tmdb_id;
    }

    public function setTmdbId(?int $tmdb_id): static
    {
        $this->tmdb_id = $tmdb_id;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function setOverview(?string $overview): static
    {
        $this->overview = $overview;
        return $this;
    }

    public function getGenres(): ?array
    {
        return $this->genres;
    }

    public function setGenres(?array $genres): static
    {
        $this->genres = $genres;
        return $this;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(?string $poster): static
    {
        $this->poster = $poster;
        return $this;
    }

    public function getReleaseDate(): ?int
    {
        return $this->release_date;
    }

    public function setReleaseDate(?int $release_date): static
    {
        $this->release_date = $release_date;
        return $this;
    }

    public function getEmbedding(): ?string
    {
        return $this->embedding;
    }

    public function setEmbedding(?string $embedding): static
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function getEmbeddingAsArray(): ?array
    {
        if (!$this->embedding) {
            return null;
        }

        // Remove the brackets and parse as floats
        $vector = trim($this->embedding, '[]');
        return array_map('floatval', explode(',', $vector));
    }

    public function setEmbeddingFromArray(array $vector): static
    {
        $this->embedding = '[' . implode(',', $vector) . ']';
        return $this;
    }
}
