<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class SettingsController extends Controller
{
    /**
     * Get the settings for the authenticated user
     */
    public function getSettings()
    {
        try {
            $user = JWTAuth::user();

            // Get settings from database
            $settings = Settings::where('user_id', $user->id)->first();

            // If no settings exist, return default settings
            if (!$settings) {
                return response()->json([
                    'success' => true,
                    'data' => $this->getDefaultSettings()
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => json_decode($settings->settings_data)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save settings for the authenticated user
     */
    public function saveSettings(Request $request)
    {
        try {
            $user = JWTAuth::user();

            // Validate the request
            $validator = Validator::make($request->all(), [
                'defaultCurrency' => 'required|string|max:3',
                'emailNotifications' => 'required|boolean',
                'smsNotifications' => 'required|boolean',
                'twoFactorAuth' => 'required|boolean',
                'sessionTimeout' => 'required|integer|min:5|max:120',
                'apiRateLimit' => 'required|integer|min:10|max:1000',
                'enableApiLogs' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find or create settings record
            $settings = Settings::updateOrCreate(
                ['user_id' => $user->id],
                ['settings_data' => json_encode($request->all())]
            );

            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully',
                'data' => json_decode($settings->settings_data)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset settings to default values
     */
    public function resetSettings()
    {
        try {
            $user = JWTAuth::user();

            // Delete existing settings
            Settings::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Settings reset to defaults',
                'data' => $this->getDefaultSettings()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default settings
     */
    private function getDefaultSettings()
    {
        return [
            'defaultCurrency' => 'USD',
            'emailNotifications' => true,
            'smsNotifications' => false,
            'twoFactorAuth' => false,
            'sessionTimeout' => 30,
            'apiRateLimit' => 100,
            'enableApiLogs' => true
        ];
    }
}
