<?php

namespace App\Storage;

use Google\Cloud\Storage\StorageClient;
use League\Flysystem\Adapter\GoogleCloudStorage;
use League\Flysystem\Filesystem;

class AppGoogleCloudStorage
{
    public function __construct()
    {
        $storageClient = new StorageClient([
            'keyFilePath' => env('GOOGLE_CLOUD_KEY_FILE_PATH'),  // Path to your service account JSON key
            'projectId' => env('GOOGLE_CLOUD_PROJECT_ID'),
        ]);

        $bucket = $storageClient->bucket(env('GOOGLE_CLOUD_BUCKET'));

        $adapter = new GoogleCloudStorage($bucket);

        return new Filesystem($adapter);
    }
}
