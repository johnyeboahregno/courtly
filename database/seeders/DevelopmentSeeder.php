<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // Create an organiser
        $organiser = User::create([
            'name' => 'Organiser',
            'email' => 'organiser@courtly.test',
            'password' => bcrypt('password'),
            'role' => 'ORGANISER',
            'email_verified_at' => now(),
        ]);

        Player::create([
            'user_id' => $organiser->id,
            'name' => $organiser->name,
            'rating' => 65.00,
            'rating_status' => 'ESTABLISHED',
            'rating_confidence' => 0.85,
            'rated_games_count' => 20,
            'total_games' => 20,
            'wins' => 12,
            'losses' => 8,
        ]);

        // Create players with varied ratings
        $ratings = [10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95];
        $playerIds = [];

        foreach ($ratings as $rating) {
            $player = Player::factory()->withRating($rating)->create(['user_id' => $organiser->id]);
            $playerIds[] = $player->id;
        }

        // Create provisional players
        for ($i = 0; $i < 6; $i++) {
            $player = Player::factory()->provisional()->create(['user_id' => $organiser->id]);
            $playerIds[] = $player->id;
        }

        // Create "Sunday Social" session with 3 courts, 14 checked-in players
        $session = Session::create([
            'name' => 'Sunday Social',
            'date' => '2026-08-09',
            'start_time' => '14:00',
            'number_of_courts' => 3,
            'status' => 'UPCOMING',
            'created_by' => $organiser->id,
        ]);

        // Create courts
        for ($i = 1; $i <= 3; $i++) {
            \App\Models\Court::create([
                'session_id' => $session->id,
                'court_number' => $i,
                'status' => 'AVAILABLE',
            ]);
        }

        // Check in 14 players
        $checkedIn = array_slice($playerIds, 0, 14);
        foreach ($checkedIn as $playerId) {
            SessionPlayer::create([
                'session_id' => $session->id,
                'player_id' => $playerId,
                'status' => 'WAITING',
                'waiting_since' => now(),
                'joined_at' => now(),
            ]);
        }

        echo "✅ DevelopmentSeeder complete: 24 players, 1 session, 3 courts, 14 checked-in.\n";
    }
}
