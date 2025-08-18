<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Google\Cloud\Storage\StorageClient;

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $path;
    protected $filename;

    /**
     * Create a new job instance.
     */
    public function __construct($path, $filename)
    {
        $this->path = $path;
        $this->filename = $filename;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (Storage::disk('public')->exists($this->path)) {
            // Move image from temp to final location
            $finalPath = 'transaction_images/' . $this->filename;
            Storage::disk('public')->move($this->path, $finalPath);
            Storage::disk('public')->delete($this->path); 

            // transfer to google cloud storage

            putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
            $storage = new StorageClient();
            $bucket = $storage->bucket(config('services.googlecloud.bucket'));
            $filePath = storage_path('app/public/transaction_images/'.$this->filename);
        
            $objectName = $this->filename;
            $path = $bucket->upload(
                fopen($filePath, 'r'), // Open the file in read mode
                [
                    'name' => 'transaction_images/'.$objectName // Set the file name in the bucket
                ]
            );
            $object = $bucket->object('transaction_images/'.$objectName);
            $object->update([
                'acl' => [
                    ['entity' => 'allUsers', 'role' => 'READER']
                ]
            ]);
            Storage::disk('public')->delete($this->path);
            Storage::disk('public')->delete($finalPath);

        }else{
            Log::error("Temp file missing: {$this->path}");
        }
        
    }
}
