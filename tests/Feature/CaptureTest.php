<?php

namespace Tests\Feature;

use App\Models\Capture;
use App\Models\User;
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
        $user = User::factory()->create();
        $captures = Capture::factory()->count(3)->create(['user_id' => $user->id]);

        // Create captures for another user (should not appear)
        Capture::factory()->count(2)->create();

        $response = $this->actingAs($user)->getJson('/api/captures');

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
        $user = User::factory()->create();
        $data = [
            'thought' => 'This is a test thought',
        ];

        $response = $this->actingAs($user)->postJson('/api/captures', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'thought', 'created_at', 'updated_at'])
            ->assertJson(['thought' => 'This is a test thought']);

        $this->assertDatabaseHas('captures', [
            'thought' => 'This is a test thought',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test can show a specific capture.
     */
    public function test_can_show_capture(): void
    {
        $user = User::factory()->create();
        $capture = Capture::factory()->create([
            'thought' => 'Specific thought to show',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/captures/{$capture->id}");

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
        $user = User::factory()->create();
        $capture = Capture::factory()->create([
            'thought' => 'Original thought',
            'user_id' => $user->id,
        ]);

        $updatedData = [
            'thought' => 'Updated thought',
        ];

        $response = $this->actingAs($user)->putJson("/api/captures/{$capture->id}", $updatedData);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'thought', 'created_at', 'updated_at'])
            ->assertJson([
                'id' => $capture->id,
                'thought' => 'Updated thought',
            ]);

        $this->assertDatabaseHas('captures', [
            'id' => $capture->id,
            'thought' => 'Updated thought',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test can delete a capture.
     */
    public function test_can_delete_capture(): void
    {
        $user = User::factory()->create();
        $capture = Capture::factory()->create([
            'thought' => 'Thought to delete',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/captures/{$capture->id}");

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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/captures', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['thought']);

        $response = $this->actingAs($user)->postJson('/api/captures', [
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
        $user = User::factory()->create();
        $capture = Capture::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/captures/{$capture->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['thought']);

        $response = $this->actingAs($user)->putJson("/api/captures/{$capture->id}", [
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/captures/99999');

        $response->assertStatus(404);

        $response = $this->actingAs($user)->putJson('/api/captures/99999', [
            'thought' => 'Updated thought',
        ]);

        $response->assertStatus(404);

        $response = $this->actingAs($user)->deleteJson('/api/captures/99999');

        $response->assertStatus(404);
    }

    /**
     * Test users cannot access other users' captures.
     */
    public function test_users_cannot_access_other_users_captures(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $capture = Capture::factory()->create(['user_id' => $user1->id]);

        // User2 cannot view user1's capture
        $response = $this->actingAs($user2)->getJson("/api/captures/{$capture->id}");
        $response->assertStatus(404);

        // User2 cannot update user1's capture
        $response = $this->actingAs($user2)->putJson("/api/captures/{$capture->id}", [
            'thought' => 'Hacked thought',
        ]);
        $response->assertStatus(404);

        // User2 cannot delete user1's capture
        $response = $this->actingAs($user2)->deleteJson("/api/captures/{$capture->id}");
        $response->assertStatus(404);

        // Verify capture still exists and unchanged
        $this->assertDatabaseHas('captures', [
            'id' => $capture->id,
            'thought' => $capture->thought,
            'user_id' => $user1->id,
        ]);
    }
}

