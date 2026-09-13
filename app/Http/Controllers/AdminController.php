<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Circle;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Render the super-admin panel (tabbed, client-rendered).
     */
    public function index(): \Illuminate\Http\Response
    {
        $base = rtrim(request()->getBasePath(), '/');
        $csrf = csrf_token();

        $__path = resource_path('views/admin.php');
        extract(['base' => $base, 'csrf' => $csrf], EXTR_SKIP);
        ob_start();
        include $__path;

        return response(ob_get_clean());
    }

    /**
     * Everything the panel needs in a single payload: aggregate counts plus
     * the four managed collections (users, circles, sessions, players).
     */
    public function data(): JsonResponse
    {
        $users = User::orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'role', 'email_verified_at', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'verified' => $u->email_verified_at !== null,
                'created_at' => $u->created_at?->toIso8601String(),
            ])->all();

        $circles = Circle::with(['admin:id,name,email'])
            ->withCount(['members', 'players', 'sessions'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Circle $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'visibility' => $c->visibility->value,
                'invite_code' => $c->invite_code,
                'admin_name' => $c->admin?->name,
                'admin_email' => $c->admin?->email,
                'members' => (int) $c->members_count,
                'players' => (int) $c->players_count,
                'sessions' => (int) $c->sessions_count,
            ])->all();

        $sessions = Session::with('circle:id,name')
            ->withCount(['courts', 'sessionPlayers'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'sport', 'status', 'date', 'circle_id', 'created_by'])
            ->map(fn (Session $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sport' => $s->sport->value,
                'status' => $s->status->value,
                'date' => $s->date?->format('Y-m-d'),
                'circle' => $s->circle?->name,
                'courts' => (int) $s->courts_count,
                'players' => (int) $s->session_players_count,
            ])->all();

        $players = Player::with('circle:id,name')
            ->orderByDesc('id')
            ->get(['id', 'name', 'rating', 'total_games', 'wins', 'circle_id', 'user_id', 'email'])
            ->map(fn (Player $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'rating' => (float) $p->rating,
                'games' => (int) $p->total_games,
                'wins' => (int) $p->wins,
                'circle' => $p->circle?->name,
                'linked_account' => $p->user_id !== null,
                'email' => $p->email,
            ])->all();

        return response()->json([
            'counts' => [
                'users' => User::count(),
                'circles' => Circle::count(),
                'sessions' => Session::count(),
                'players' => Player::count(),
                'matches' => GameMatch::count(),
            ],
            'users' => $users,
            'circles' => $circles,
            'sessions' => $sessions,
            'players' => $players,
        ]);
    }

    /**
     * Change a user's role. The authenticated super admin cannot demote
     * themselves (to avoid locking themselves out of the panel).
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot change your own role.'], 422);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
        ]);

        $user->role = UserRole::from($validated['role']);
        $user->save();

        return response()->json(['message' => 'Role updated.']);
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        try {
            $user->delete();
            return response()->json(['message' => 'User deleted.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not delete user: '.$this->deleteFailureReason($e)], 500);
        }
    }

    public function destroyCircle(Circle $circle): JsonResponse
    {
        try {
            $circle->delete();
            return response()->json(['message' => 'Circle deleted.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not delete circle: '.$this->deleteFailureReason($e)], 500);
        }
    }

    public function destroySession(Session $session): JsonResponse
    {
        try {
            $session->delete();
            return response()->json(['message' => 'Session deleted.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not delete session: '.$this->deleteFailureReason($e)], 500);
        }
    }

    public function destroyPlayer(Player $player): JsonResponse
    {
        try {
            $player->delete();
            return response()->json(['message' => 'Player deleted.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not delete player: '.$this->deleteFailureReason($e)], 500);
        }
    }

    private function deleteFailureReason(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Database\QueryException) {
            if (stripos($e->getMessage(), 'foreign key') !== false) {
                return 'it still has related records that prevent deletion';
            }
            return 'a database constraint prevented the deletion';
        }
        return 'an unexpected error occurred';
    }

    /**
     * The super admin changes their own password (requires the current one).
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json(['message' => 'Password updated.']);
    }
}
