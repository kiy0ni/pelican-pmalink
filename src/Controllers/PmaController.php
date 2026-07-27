<?php

namespace KiyOni\PmaLink\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PmaController extends Controller
{
    public function redirect($database)
    {
        $db = \App\Models\Database::findOrFail($database);
        $token = Str::random(60);
        
        $credentials = [
            'user' => $db->username,
            'password' => $db->password,
            'host' => $db->host->host,
        ];
        
        Cache::put('pma_sso_' . $token, $credentials, 60);
        
        $pmaUrl = env('PMALINK_PMA_URL');
        if (!$pmaUrl) {
            return redirect()->back()->withErrors('The phpMyAdmin URL is not configured. Please go to Plugins -> PmaLink -> Settings.');
        }

        return redirect()->away($pmaUrl . '/signon.php?token=' . $token);
    }
}
