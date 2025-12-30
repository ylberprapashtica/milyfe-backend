<?php

namespace Tests\Feature;

use App\Models\Capture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test can list all captures.
     */
    public function test_can_list_all_captures(): void
    {
        $captures = Capture::factory()->count(3)->create();

        $response = $this->getJson('/api/captures');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => ['id', 'thought', 'created_at', 'updated_at'],
            ]);
    }

    /**
     * Test can create a capture.
     */
    public function test_can_create_capture(): void
    {
        $data = [
            'thought' => 'This is a test thought',
        ];

        $response = $this->postJson('/api/captures', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'thought', 'created_at', 'updated_at'])
            ->assertJson(['thought' => 'This is a test thought']);

        $this->assertDatabaseHas('captures', [
            'thought' => 'This is a test thought',
        ]);
    }

    /**
     * Test can show a specific capture.
     */
    public function test_can_show_capture(): void
    {
        $capture = Capture::factory()->create([
            'thought' => 'Specific thought to show',
        ]);

        $response = $this->getJson("/api/captures/{$capture->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'thought', 'created_at', 'updated_at'])
            ->assertJson([
                'id' => $capture->id,
                'thought' => 'Specific thought to show',
            ]);
    }

    /**
     * Test can update a capture.
     */
    public function test_can_update_capture(): void
    {
        $capture = Capture::factory()->create([
            'thought' => 'Original thought',
        ]);

        $updatedData = [
            'thought' => 'Updated thought',
        ];

        $response = $this->putJson("/api/captures/{$capture->id}", $updatedData);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'thought', 'created_at', 'updated_at'])
            ->assertJson([
                'id' => $capture->id,
                'thought' => 'Updated thought',
            ]);

        $this->assertDatabaseHas('captures', [
            'id' => $capture->id,
            'thought' => 'Updated thought',
        ]);
    }

    /**
     * Test can delete a capture.
     */
    public function test_can_delete_capture(): void
    {
        $capture = Capture::factory()->create([
            'thought' => 'Thought to delete',
        ]);

        $response = $this->deleteJson("/api/captures/{$capture->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('captures', [
            'id' => $capture->id,
        ]);
    }

    /**
     * Test validation on create.
     */
    public function test_validation_on_create(): void
    {
        $response = $this->postJson('/api/captures', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['thought']);

        $response = $this->postJson('/api/captures', [
            'thought' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['thought']);
    }

    /**
     * Test validation on update.
     */
    public function test_validation_on_update(): void
    {
        $capture = Capture::factory()->create();

        $response = $this->putJson("/api/captures/{$capture->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['thought']);

        $response = $this->putJson("/api/captures/{$capture->id}", [
            'thought' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['thought']);
    }

    /**
     * Test returns 404 for nonexistent capture.
     */
    public function test_returns_404_for_nonexistent_capture(): void
    {
        $response = $this->getJson('/api/captures/99999');

        $response->assertStatus(404);

        $response = $this->putJson('/api/captures/99999', [
            'thought' => 'Updated thought',
        ]);

        $response->assertStatus(404);

        $response = $this->deleteJson('/api/captures/99999');

        $response->assertStatus(404);
    }
}

