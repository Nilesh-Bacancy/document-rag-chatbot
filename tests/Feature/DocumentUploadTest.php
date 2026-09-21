<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pdf_can_be_uploaded_and_is_queued_for_processing(): void
    {
        Storage::fake('local');
        Bus::fake();

        $file = UploadedFile::fake()->create('handbook.pdf', 100, 'application/pdf');

        $response = $this->post('/documents', ['document' => $file]);

        $response->assertRedirect('/documents');

        $this->assertDatabaseHas('documents', [
            'original_filename' => 'handbook.pdf',
            'status' => Document::STATUS_PENDING,
        ]);

        Bus::assertDispatched(ProcessDocumentJob::class);
    }

    public function test_a_non_pdf_file_is_rejected(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('handbook.docx', 100, 'application/msword');

        $response = $this->post('/documents', ['document' => $file]);

        $response->assertSessionHasErrors('document');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_document_is_required(): void
    {
        $response = $this->post('/documents', []);

        $response->assertSessionHasErrors('document');
    }

    public function test_a_document_can_be_removed_along_with_its_stored_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/removable.pdf', 'fake pdf contents');

        $document = Document::factory()->create(['file_path' => 'documents/removable.pdf']);

        $response = $this->delete("/documents/{$document->id}");

        $response->assertRedirect('/documents');
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing('documents/removable.pdf');
    }
}
