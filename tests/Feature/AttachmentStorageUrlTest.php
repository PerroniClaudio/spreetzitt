<?php

namespace Tests\Feature;

use App\Http\Controllers\FileUploadController;
use App\Models\ContractAttachment;
use App\Models\HardwareAttachment;
use App\Models\SoftwareAttachment;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AttachmentStorageUrlTest extends TestCase
{
    public function test_attachment_urls_use_the_stored_key_for_s3_and_gcs(): void
    {
        foreach (['s3', 'private', 'gcs'] as $diskName) {
            config(['app.object_storage_method' => $diskName === 'gcs' ? 'gcs' : 'default', 'filesystems.default' => $diskName, 'filesystems.storage_path_prefix' => 'tickets']);
            $disk = Mockery::mock();
            Storage::shouldReceive('disk')->with($diskName)->andReturn($disk);

            foreach ([HardwareAttachment::class => 'hardware', SoftwareAttachment::class => 'software', ContractAttachment::class => 'contracts'] as $model => $directory) {
                $path = "$directory/42/file.pdf";
                $attachment = new $model(['file_path' => $path, 'original_filename' => 'file.pdf', 'file_extension' => 'pdf']);
                foreach (['getDownloadUrl' => 'attachment', 'getPreviewUrl' => 'inline'] as $method => $disposition) {
                    $disk->shouldReceive('temporaryUrl')->once()->with($path, Mockery::any(), ['ResponseContentDisposition' => "$disposition; filename=\"file.pdf\""])->andReturn('signed-url');
                    $this->assertSame('signed-url', $attachment->$method());
                }
            }
        }
    }

    public function test_legacy_s3_urls_keep_the_configured_prefix(): void
    {
        config(['app.object_storage_method' => 'default', 'filesystems.default' => 's3', 'filesystems.storage_path_prefix' => 'tickets']);
        $disk = Mockery::mock();
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
        $disk->shouldReceive('temporaryUrl')->once()->with('tickets/report.pdf', Mockery::any(), [])->andReturn('signed-url');

        $this->assertSame('signed-url', FileUploadController::generateSignedUrlForFile('report.pdf'));
    }
}
