<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateMobileApiKey extends Command
{
    protected $signature = 'mobile:generate-api-key 
                            {user : User ID or email}
                            {--name= : API key name}
                            {--platform=android : Platform (android, ios, web)}
                            {--device-id= : Device ID}
                            {--expires=30 : Days until expiration}
                            {--permissions=* : Permissions (comma-separated)}';

    protected $description = 'Generate API key for mobile app access';

    public function handle()
    {
        try {
            // Find user
            $userIdentifier = $this->argument('user');
            $user = is_numeric($userIdentifier) 
                ? User::find($userIdentifier)
                : User::where('email', $userIdentifier)->first();

            if (!$user) {
                $this->error("User not found: {$userIdentifier}");
                return 1;
            }

            // Generate API key
            $apiKey = $this->generateApiKey($user);

            // Display results
            $this->info('API Key generated successfully!');
            $this->newLine();
            
            $this->table(
                ['Field', 'Value'],
                [
                    ['User', $user->name . ' (' . $user->email . ')'],
                    ['API Key', $apiKey->key],
                    ['Name', $apiKey->name],
                    ['Platform', $apiKey->platform],
                    ['Device ID', $apiKey->device_id ?? 'N/A'],
                    ['Expires At', $apiKey->expires_at->format('Y-m-d H:i:s')],
                    ['Permissions', implode(', ', $apiKey->permissions ?? [])]
                ]
            );

            $this->newLine();
            $this->warn('⚠️  Keep this API key secure! It will not be shown again.');
            $this->info('Use this key in mobile app headers: X-API-Key: ' . $apiKey->key);

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to generate API key: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Generate API key for user
     */
    private function generateApiKey(User $user): ApiKey
    {
        // Deactivate existing keys for this device if device ID is provided
        $deviceId = $this->option('device-id');
        if ($deviceId) {
            ApiKey::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->update(['is_active' => false]);
        }

        // Create new API key
        return ApiKey::create([
            'user_id' => $user->id,
            'key' => 'vk_' . Str::random(48),
            'name' => $this->option('name') ?? "Mobile App - {$this->option('platform')}",
            'client_type' => 'mobile',
            'platform' => $this->option('platform'),
            'device_id' => $deviceId,
            'app_version' => '1.0',
            'permissions' => $this->parsePermissions(),
            'is_active' => true,
            'expires_at' => now()->addDays((int)$this->option('expires')),
            'last_used_at' => now()
        ]);
    }

    /**
     * Parse permissions from command options
     */
    private function parsePermissions(): array
    {
        $permissions = $this->option('permissions');
        
        if (is_array($permissions) && in_array('*', $permissions)) {
            // All permissions for admin users
            return ['*'];
        }

        if (is_string($permissions)) {
            return array_map('trim', explode(',', $permissions));
        }

        return [];
    }
} 