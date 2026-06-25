<?php

namespace App\Support\Cloudinary;

use Cloudinary\Cloudinary as CloudinarySdk;

/**
 * Thin adapter over the official Cloudinary PHP SDK that preserves the small
 * surface of the old cloudinary-labs/cloudinary-laravel facade we relied on
 * (Cloudinary::uploadFile($source)->getSecurePath()). The Laravel wrapper has
 * no Laravel 13 release, so we drive the official SDK directly.
 */
class CloudinaryManager
{
    protected ?CloudinarySdk $sdk = null;

    /**
     * Upload a local path, remote URL, or base64 payload to Cloudinary.
     */
    public function uploadFile(string $source, array $options = []): CloudinaryUploadResult
    {
        $response = $this->sdk()->uploadApi()->upload($source, array_merge([
            'resource_type' => 'auto',
        ], $options));

        return new CloudinaryUploadResult((array) $response->getArrayCopy());
    }

    /**
     * Lazily build the SDK so an empty CLOUDINARY_URL (local/preprod) doesn't
     * throw on resolution — only when an upload is actually attempted.
     */
    public function sdk(): CloudinarySdk
    {
        return $this->sdk ??= new CloudinarySdk((string) config('cloudinary.cloud_url'));
    }
}
