<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrackingService
{
    /**
     * Get tracking details for a specific airway bill (resi) and courier.
     * Integrates with BinderByte API if API key is present.
     * Otherwise, returns realistic mock data for local development/testing.
     *
     * @param string $awb
     * @param string $courier
     * @return array
     */
    public function getTrackingInfo(string $awb, string $courier): array
    {
        $apiKey = env('BINDERBYTE_API_KEY');

        // If no API key is provided, return simulated tracking data.
        if (empty($apiKey) || env('APP_ENV') === 'local') {
            return $this->getMockTrackingData($awb, $courier);
        }

        // Live API call to BinderByte
        try {
            $response = Http::get('https://api.binderbyte.com/v1/track', [
                'api_key' => $apiKey,
                'courier' => strtolower($courier),
                'awb' => $awb
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] == 200) {
                    return [
                        'success' => true,
                        'data' => $data['data']
                    ];
                }
            }

            Log::error('Tracking API error', ['response' => $response->body()]);
            return [
                'success' => false,
                'message' => 'Gagal melacak resi dari server ekspedisi.'
            ];

        } catch (\Exception $e) {
            Log::error('Tracking API Exception', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat melacak resi.'
            ];
        }
    }

    /**
     * Generate dynamic mock tracking data based on AWB input for testing.
     */
    private function getMockTrackingData(string $awb, string $courier): array
    {
        // Simulate a slight delay as if hitting an API
        sleep(1);

        $now = now();

        return [
            'success' => true,
            'data' => [
                'summary' => [
                    'awb' => $awb,
                    'courier' => strtoupper($courier),
                    'service' => 'REG',
                    'status' => 'DELIVERED',
                    'date' => $now->subDays(3)->format('Y-m-d H:i:s'),
                    'weight' => '1 kg',
                    'amount' => '0',
                ],
                'detail' => [
                    'origin' => 'JAKARTA',
                    'destination' => 'SURABAYA',
                    'shipper' => 'RAVELLA OFFICIAL',
                    'receiver' => 'JOHN DOE',
                ],
                'history' => [
                    [
                        'date' => $now->format('Y-m-d H:i:s'),
                        'desc' => 'Paket telah diterima oleh [JOHN DOE] - (Ybs)',
                        'location' => 'SURABAYA'
                    ],
                    [
                        'date' => $now->subHours(5)->format('Y-m-d H:i:s'),
                        'desc' => 'Paket sedang dibawa kurir menuju alamat tujuan',
                        'location' => 'SURABAYA'
                    ],
                    [
                        'date' => $now->subDays(1)->format('Y-m-d H:i:s'),
                        'desc' => 'Paket tiba di hub/gudang transit [SURABAYA]',
                        'location' => 'SURABAYA'
                    ],
                    [
                        'date' => $now->subDays(2)->format('Y-m-d H:i:s'),
                        'desc' => 'Paket diberangkatkan dari kota asal [JAKARTA]',
                        'location' => 'JAKARTA'
                    ],
                    [
                        'date' => $now->subDays(3)->format('Y-m-d H:i:s'),
                        'desc' => 'Paket telah diserahkan ke pihak ekspedisi',
                        'location' => 'JAKARTA'
                    ]
                ]
            ]
        ];
    }
}
