<?php

namespace KiyOni\PmaLink\Controllers;

use App\Enums\SubuserPermission;
use App\Models\Database;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class PmaController extends Controller
{
    private const CACHE_PREFIX = 'pmalink:sso:';

    private const TTL_SECONDS = 60;

    public function redirect(Request $request, Database $database)
    {
        $user = $request->user();
        $server = $database->server;

        abort_if($server === null, 404);
        abort_unless($user->can(SubuserPermission::DatabaseRead, $server), 403);

        $pmaUrl = config('pmalink.pma_url');

        if (blank($pmaUrl)) {
            return back()->withErrors('The phpMyAdmin URL is not configured.');
        }

        $token = Str::random(64);

        $payload = Crypt::encryptString(json_encode([
            'host' => $database->host->host,
            'port' => $database->host->port,
            'user' => $database->username,
            'password' => $database->password,
            'db' => $database->database,
        ]));

        Cache::put(
            self::CACHE_PREFIX . hash('sha256', $token),
            $payload,
            now()->addSeconds(self::TTL_SECONDS)
        );

        return redirect()->away(rtrim($pmaUrl, '/') . '/signon.php?token=' . $token);
    }

    public function verify(Request $request, string $token): JsonResponse
    {
        $secret = config('pmalink.verify_secret');

        abort_if(blank($secret), 503);
        abort_unless(
            hash_equals($secret, (string) $request->header('X-PmaLink-Secret')),
            403
        );

        $payload = Cache::pull(self::CACHE_PREFIX . hash('sha256', $token));

        if (blank($payload)) {
            return response()->json(['error' => 'Token not found or expired'], 404);
        }

        try {
            $data = json_decode(Crypt::decryptString($payload), true);
        } catch (DecryptException) {
            return response()->json(['error' => 'Corrupted payload'], 500);
        }

        return response()->json($data);
    }
}
