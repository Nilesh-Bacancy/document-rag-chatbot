<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Message;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_question_cannot_be_asked_while_the_document_is_still_processing(): void
    {
        $document = Document::factory()->create(['status' => Document::STATUS_PROCESSING]);

        $response = $this->post("/documents/{$document->id}/chat", [
            'question' => 'What is the leave policy?',
        ]);

        $response->assertSessionHasErrors('question');
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_asking_a_question_stores_both_messages_and_returns_the_llm_answer(): void
    {
        $document = Document::factory()->create(['status' => Document::STATUS_COMPLETED]);

        $this->mock(RagService::class, function ($mock) {
            $mock->shouldReceive('ask')
                ->once()
                ->andReturn('Employees are allowed 12 casual leaves per year.');
        });

        $response = $this->post("/documents/{$document->id}/chat", [
            'question' => 'How many casual leaves are allowed?',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'role' => Message::ROLE_USER,
            'content' => 'How many casual leaves are allowed?',
        ]);

        $this->assertDatabaseHas('messages', [
            'role' => Message::ROLE_ASSISTANT,
            'content' => 'Employees are allowed 12 casual leaves per year.',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
