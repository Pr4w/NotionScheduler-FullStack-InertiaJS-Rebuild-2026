<?php

namespace App\Support\Cloudinary;

/**
 * Mirrors the result object returned by the old cloudinary-labs facade so call
 * sites can keep using ->getSecurePath() unchanged.
 */
class CloudinaryUploadResult
{
    public function __construct(protected array $data) {}

    public function getSecurePath(): ?string
    {
        return $this->data['secure_url'] ?? null;
    }

    public function getPath(): ?string
    {
        return $this->data['url'] ?? null;
    }

    public function getPublicId(): ?string
    {
        return $this->data['public_id'] ?? null;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
