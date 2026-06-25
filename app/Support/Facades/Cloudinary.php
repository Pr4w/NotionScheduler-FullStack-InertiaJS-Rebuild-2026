<?php

namespace App\Support\Facades;

use App\Support\Cloudinary\CloudinaryManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Support\Cloudinary\CloudinaryUploadResult uploadFile(string $source, array $options = [])
 *
 * @see CloudinaryManager
 */
class Cloudinary extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CloudinaryManager::class;
    }
}
