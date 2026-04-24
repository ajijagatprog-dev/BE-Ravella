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

        $formattedTarget = $this->formatPhoneNumber($target);

        try {
            Log::info("Sending WhatsApp to {$formattedTarget} via Fonnte");

            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->asForm()->post('https://api.fonnte.com/send', [
                'target' => $formattedTarget,
                'message' => $message,
                'delay' => '1',
            ]);

            $httpStatus = $response->status();
            $rawBody = $response->body();
            $result = $response->json();

            Log::info("Fonnte HTTP {$httpStatus} for {$formattedTarget}: {$rawBody}");

            if (isset($result['status']) && $result['status'] == true) {
                Log::info("WhatsApp sent successfully to {$formattedTarget}");
                return true;
            }

            Log::error("Fonnte failed for {$formattedTarget}: HTTP {$httpStatus} — {$rawBody}");
            return false;

        } catch (\Exception $e) {
            Log::error('Failed to connect to Fonnte API: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format phone number to international format (starting with 62)
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If it starts with 0, replace with 62
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        // If it starts with 8 (local format without leading 0), add 62
        if (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}
