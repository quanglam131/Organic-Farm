<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads product photos, farm diary media and complaint images.
 * Uses Cloudinary (signed upload over HTTP) when configured, otherwise the local public disk.
 */
class MediaStorage
{
    /** @param  'image'|'video'  $resourceType */
    public function store(UploadedFile $file, string $folder, string $resourceType = 'image'): string
    {
        $cloud = config('cloudinary.cloud_name');
        $key = config('cloudinary.api_key');
        $secret = config('cloudinary.api_secret');

        if (filled($cloud) && filled($key) && filled($secret)) {
            return $this->uploadToCloudinary($file, $folder, $resourceType, $cloud, $key, $secret);
        }

        return $this->storeLocally($file, $folder);
    }

    /** Relative URL (/storage/...) so it works on any host and through the dev proxy. */
    private function storeLocally(UploadedFile $file, string $folder): string
    {
        return '/storage/'.$file->store($folder, 'public');
    }

    private function uploadToCloudinary(
        UploadedFile $file,
        string $folder,
        string $resourceType,
        string $cloud,
        string $key,
        string $secret,
    ): string {
        $params = ['folder' => $folder, 'timestamp' => time()];

        // Tuần 7: photos are resized (max 1600 px) and recompressed by Cloudinary before they are stored.
        if ($resourceType === 'image') {
            $params['transformation'] = 'c_limit,w_1600,h_1600/q_auto';
        }

        // Cloudinary signature: sorted params as key=value joined by &, plus the API secret, SHA-1.
        ksort($params);
        $toSign = collect($params)->map(fn ($value, $name) => "{$name}={$value}")->implode('&');
        $signature = sha1($toSign.$secret);

        try {
            $response = Http::attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post("https://api.cloudinary.com/v1_1/{$cloud}/{$resourceType}/upload", [
                    ...$params,
                    'api_key' => $key,
                    'signature' => $signature,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            // Fall back to local storage so an upload failure does not block the farm's workflow.
            report($e);

            return $this->storeLocally($file, $folder);
        }

        return $response['secure_url'];
    }
}
