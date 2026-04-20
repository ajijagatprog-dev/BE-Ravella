<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    /**
     * Send a WhatsApp message via Fonnte API
     *
     * @param string $target Phone number to send to
     * @param string $message The message body
     * @return bool True if success, False otherwise
     */
    public function sendMessage(string $target, string $message): bool
    {
        $token = config('fonnte.token');

        if (empty($token)) {
            Log::warning('Fonnte token is missing. WhatsApp notification not sent.');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post('https://api.fonnte.com/send', [
                'target' => $target,
                'message' => $message,
                'delay' => '1',
            ]);

            $result = $response->json();

            if (isset($result['status']) && $result['status'] == true) {
                return true;
            }

            Log::error('Fonnte Error Response: ' . json_encode($result));
            return false;

        } catch (\Exception $e) {
            Log::error('Failed to connect to Fonnte API: ' . $e->getMessage());
            return false;
        }
    }
}
