<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MobilePushNotificationService
{
    private const FCM_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';
    private const APNS_ENDPOINT = 'https://api.push.apple.com/3/device/';

    /**
     * Send push notification to user
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $deviceTokens = DeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($deviceTokens->isEmpty()) {
            return [
                'success' => false,
                'error' => 'No active device tokens found for user'
            ];
        }

        $results = [];
        foreach ($deviceTokens as $deviceToken) {
            $result = $this->sendToDevice($deviceToken, $title, $body, $data);
            $results[] = $result;
        }

        return [
            'success' => true,
            'results' => $results,
            'total_devices' => count($deviceTokens)
        ];
    }

    /**
     * Send push notification to specific device
     */
    public function sendToDevice(DeviceToken $deviceToken, string $title, string $body, array $data = []): array
    {
        try {
            $notification = PushNotification::create([
                'user_id' => $deviceToken->user_id,
                'device_token_id' => $deviceToken->id,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'status' => 'pending'
            ]);

            $result = match($deviceToken->platform) {
                'android' => $this->sendToFCM($deviceToken->token, $title, $body, $data),
                'ios' => $this->sendToAPNS($deviceToken->token, $title, $body, $data),
                default => throw new \InvalidArgumentException("Unsupported platform: {$deviceToken->platform}")
            };

            // Update notification status
            $notification->update([
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['error'] ?? null,
                'sent_at' => now()
            ]);

            // Update device token last used
            $deviceToken->update(['last_used_at' => now()]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Push notification failed', [
                'device_token_id' => $deviceToken->id,
                'platform' => $deviceToken->platform,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $results = [];
        foreach ($userIds as $userId) {
            $result = $this->sendToUser($userId, $title, $body, $data);
            $results[] = [
                'user_id' => $userId,
                'result' => $result
            ];
        }

        return [
            'success' => true,
            'results' => $results,
            'total_users' => count($userIds)
        ];
    }

    /**
     * Send notification to all users
     */
    public function sendToAllUsers(string $title, string $body, array $data = []): array
    {
        $userIds = \App\Models\User::pluck('id')->toArray();
        return $this->sendToUsers($userIds, $title, $body, $data);
    }

    /**
     * Send notification to users by role
     */
    public function sendToUsersByRole(string $role, string $title, string $body, array $data = []): array
    {
        $userIds = \App\Models\User::where('role', $role)->pluck('id')->toArray();
        return $this->sendToUsers($userIds, $title, $body, $data);
    }

    /**
     * Send FCM notification (Android)
     */
    private function sendToFCM(string $token, string $title, string $body, array $data = []): array
    {
        try {
            $fcmKey = config('services.fcm.server_key');
            
            if (!$fcmKey) {
                throw new \Exception('FCM server key not configured');
            }

            $payload = [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => 1
                ],
                'data' => $data,
                'priority' => 'high',
                'content_available' => true
            ];

            $response = Http::withHeaders([
                'Authorization' => "key={$fcmKey}",
                'Content-Type' => 'application/json'
            ])->post(self::FCM_ENDPOINT, $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                if (isset($result['success']) && $result['success'] == 1) {
                    return ['success' => true];
                } else {
                    return [
                        'success' => false,
                        'error' => $result['results'][0]['error'] ?? 'FCM delivery failed'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => 'FCM request failed: ' . $response->status()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'FCM error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send APNS notification (iOS)
     */
    private function sendToAPNS(string $token, string $title, string $body, array $data = []): array
    {
        try {
            $apnsKey = config('services.apns.key');
            $apnsKeyId = config('services.apns.key_id');
            $apnsTeamId = config('services.apns.team_id');
            $apnsBundleId = config('services.apns.bundle_id');
            
            if (!$apnsKey || !$apnsKeyId || !$apnsTeamId || !$apnsBundleId) {
                throw new \Exception('APNS configuration incomplete');
            }

            $payload = [
                'aps' => [
                    'alert' => [
                        'title' => $title,
                        'body' => $body
                    ],
                    'sound' => 'default',
                    'badge' => 1,
                    'content-available' => 1
                ],
                'data' => $data
            ];

            $jwt = $this->generateAPNSJWT($apnsKey, $apnsKeyId, $apnsTeamId);
            $url = self::APNS_ENDPOINT . $token;

            $response = Http::withHeaders([
                'Authorization' => "bearer {$jwt}",
                'apns-topic' => $apnsBundleId,
                'apns-push-type' => 'alert',
                'Content-Type' => 'application/json'
            ])->post($url, $payload);

            if ($response->successful()) {
                return ['success' => true];
            } else {
                $error = $response->json();
                return [
                    'success' => false,
                    'error' => $error['reason'] ?? 'APNS delivery failed'
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'APNS error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate JWT for APNS authentication
     */
    private function generateAPNSJWT(string $key, string $keyId, string $teamId): string
    {
        $header = [
            'alg' => 'ES256',
            'kid' => $keyId
        ];

        $payload = [
            'iss' => $teamId,
            'iat' => time()
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = '';
        openssl_sign(
            $headerEncoded . '.' . $payloadEncoded,
            $signature,
            $key,
            'SHA256'
        );
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Register device token
     */
    public function registerDeviceToken(int $userId, string $token, string $platform, array $deviceInfo = []): array
    {
        try {
            // Check if token already exists
            $existingToken = DeviceToken::where('token', $token)->first();
            
            if ($existingToken) {
                // Update existing token
                $existingToken->update([
                    'user_id' => $userId,
                    'platform' => $platform,
                    'device_info' => $deviceInfo,
                    'is_active' => true,
                    'last_used_at' => now()
                ]);

                return [
                    'success' => true,
                    'action' => 'updated',
                    'device_token_id' => $existingToken->id
                ];
            }

            // Create new token
            $deviceToken = DeviceToken::create([
                'user_id' => $userId,
                'token' => $token,
                'platform' => $platform,
                'device_info' => $deviceInfo,
                'is_active' => true,
                'registered_at' => now(),
                'last_used_at' => now()
            ]);

            return [
                'success' => true,
                'action' => 'created',
                'device_token_id' => $deviceToken->id
            ];

        } catch (\Exception $e) {
            Log::error('Device token registration failed', [
                'user_id' => $userId,
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Unregister device token
     */
    public function unregisterDeviceToken(string $token): array
    {
        try {
            $deviceToken = DeviceToken::where('token', $token)->first();
            
            if (!$deviceToken) {
                return [
                    'success' => false,
                    'error' => 'Device token not found'
                ];
            }

            $deviceToken->update(['is_active' => false]);

            return [
                'success' => true,
                'action' => 'unregistered'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats(): array
    {
        $totalNotifications = PushNotification::count();
        $sentNotifications = PushNotification::where('status', 'sent')->count();
        $deliveredNotifications = PushNotification::where('status', 'delivered')->count();
        $failedNotifications = PushNotification::where('status', 'failed')->count();

        return [
            'total' => $totalNotifications,
            'sent' => $sentNotifications,
            'delivered' => $deliveredNotifications,
            'failed' => $failedNotifications,
            'delivery_rate' => $totalNotifications > 0 ? ($deliveredNotifications / $totalNotifications) * 100 : 0
        ];
    }
} 