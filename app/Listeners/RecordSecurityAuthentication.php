<?php

namespace App\Listeners;
use App\Models\LoginAttempt;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;


class RecordSecurityAuthentication
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login|Failed $event): void
    {
        $request = request();
        $status = $event instanceof Login ? 'success' : 'failed';
        $identifier = $event instanceof Login   ? (string) $event->user->getAuthIdentifier() 
         : (string) ($event->credentials['email'] ?? ''); 
     LoginAttempt::create([ 
         'identifier_hash' => hash('sha256', mb_strtolower($identifier)), 
         'ip_address' => $request->ip(), 
         'status' => $status, 
         'user_agent' => substr($request->userAgent() ?? '', 0, 255), 
         'created_at' => now(), 
     ]); 
     Log::channel('security')->log( 
         $status === 'success' ? 'info' : 'warning', 
         'Authentication '.$status, 
         ['ip' => $request->ip(), 'guard' => $event->guard] 
     ); 
 } 
    }

