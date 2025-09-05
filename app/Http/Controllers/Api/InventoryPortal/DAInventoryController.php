<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\InventoryMovement;
use App\Models\Bin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DAInventoryController extends Controller
{
    /**
     * Get pending inventory confirmations
     */
    public function pendingConfirmations(Request $request)
    {
        try {
            $user = $request->user();
            $agent = DeliveryAgent::where('user_id', $user->id)->first();
            
            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery agent profile not found'
                ], 404);
            }

            // Get the last delivery sent to this agent
            $lastDelivery = InventoryMovement::where('to_agent_id', $agent->id)
                ->where('type', 'delivery')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastDelivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending confirmations found'
                ], 404);
            }

            // Get current agent inventory
            $agentBin = Bin::where('delivery_agent_id', $agent->id)->first();
            $currentInventory = $agentBin ? [
                'shampoo_count' => $agentBin->shampoo_count ?? 0,
                'pomade_count' => $agentBin->pomade_count ?? 0,
                'conditioner_count' => $agentBin->conditioner_count ?? 0
            ] : [
                'shampoo_count' => 0,
                'pomade_count' => 0,
                'conditioner_count' => 0
            ];

            $pendingItems = [
                [
                    'item_id' => 1,
                    'name' => 'Fulani Shampoo',
                    'qty_sent' => 10,
                    'qty_received' => null,
                    'previous_qty' => $currentInventory['shampoo_count'],
                    'total_now' => $currentInventory['shampoo_count'] + 10
                ],
                [
                    'item_id' => 2,
                    'name' => 'Fulani Pomade',
                    'qty_sent' => 6,
                    'qty_received' => null,
                    'previous_qty' => $currentInventory['pomade_count'],
                    'total_now' => $currentInventory['pomade_count'] + 6
                ],
                [
                    'item_id' => 3,
                    'name' => 'Conditioner',
                    'qty_sent' => 1,
                    'qty_received' => null,
                    'previous_qty' => $currentInventory['conditioner_count'],
                    'total_now' => $currentInventory['conditioner_count'] + 1
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'pending_confirmations' => [
                        [
                            'id' => $lastDelivery->id,
                            'date' => $lastDelivery->created_at->format('Y-m-d'),
                            'items' => $pendingItems
                        ]
                    ],
                    'last_delivery' => [
                        'date' => $lastDelivery->created_at->format('Y-m-d'),
                        'items' => $pendingItems
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending confirmations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm received inventory
     */
    public function confirmReceived(Request $request)
    {
        try {
            $user = $request->user();
            $agent = DeliveryAgent::where('user_id', $user->id)->first();
            
            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery agent profile not found'
                ], 404);
            }

            $request->validate([
                'items' => 'required|array',
                'items.*.item_id' => 'required|integer',
                'items.*.qty_received' => 'required|integer|min:0',
                'expenses.transport' => 'nullable|integer|min:0|max:1500',
                'expenses.storekeeper_fee' => 'nullable|integer|min:0|max:1000',
                'proof_photo' => 'nullable|string'
            ]);

            $items = $request->input('items');
            $expenses = $request->input('expenses', []);
            $proofPhoto = $request->input('proof_photo');

            // Update agent's bin inventory
            $agentBin = Bin::where('delivery_agent_id', $agent->id)->first();
            
            if (!$agentBin) {
                $agentBin = Bin::create([
                    'delivery_agent_id' => $agent->id,
                    'shampoo_count' => 0,
                    'pomade_count' => 0,
                    'conditioner_count' => 0
                ]);
            }

            $updatedInventory = [];
            foreach ($items as $item) {
                switch ($item['item_id']) {
                    case 1: // Shampoo
                        $agentBin->shampoo_count += $item['qty_received'];
                        $updatedInventory['shampoo'] = $agentBin->shampoo_count;
                        break;
                    case 2: // Pomade
                        $agentBin->pomade_count += $item['qty_received'];
                        $updatedInventory['pomade'] = $agentBin->pomade_count;
                        break;
                    case 3: // Conditioner
                        $agentBin->conditioner_count += $item['qty_received'];
                        $updatedInventory['conditioner'] = $agentBin->conditioner_count;
                        break;
                }
            }

            $agentBin->save();

            // Log the confirmation
            DB::table('inventory_confirmations')->insert([
                'delivery_agent_id' => $agent->id,
                'items_confirmed' => json_encode($items),
                'expenses' => json_encode($expenses),
                'proof_photo' => $proofPhoto,
                'confirmed_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'confirmed',
                    'updated_inventory' => $updatedInventory,
                    'expenses' => $expenses
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm received inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Friday stock check information
     */
    public function fridayCheck(Request $request)
    {
        try {
            $user = $request->user();
            $agent = DeliveryAgent::where('user_id', $user->id)->first();
            
            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery agent profile not found'
                ], 404);
            }

            // Calculate next Friday deadline
            $nextFriday = Carbon::now()->next(Carbon::FRIDAY)->setTime(12, 0, 0);
            $timeRemaining = $nextFriday->diff(Carbon::now());
            
            // Get last upload
            $lastUpload = DB::table('stock_photos')
                ->where('delivery_agent_id', $agent->id)
                ->orderBy('uploaded_at', 'desc')
                ->first();

            $status = 'pending';
            if ($lastUpload) {
                $lastUploadDate = Carbon::parse($lastUpload->uploaded_at);
                if ($lastUploadDate->isFriday() && $lastUploadDate->isThisWeek()) {
                    $status = 'completed';
                } elseif ($lastUploadDate->isFriday() && $lastUploadDate->isLastWeek()) {
                    $status = 'overdue';
                }
            }

            // Get current stock levels
            $agentBin = Bin::where('delivery_agent_id', $agent->id)->first();
            $currentStock = $agentBin ? [
                'shampoo' => $agentBin->shampoo_count ?? 0,
                'pomade' => $agentBin->pomade_count ?? 0,
                'conditioner' => $agentBin->conditioner_count ?? 0
            ] : [
                'shampoo' => 0,
                'pomade' => 0,
                'conditioner' => 0
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'deadline' => $nextFriday->format('Y-m-d H:i:s'),
                    'time_remaining' => sprintf('%dd %dh %dm', 
                        $timeRemaining->days, 
                        $timeRemaining->h, 
                        $timeRemaining->i
                    ),
                    'last_upload' => $lastUpload ? $lastUpload->uploaded_at : null,
                    'status' => $status,
                    'current_stock' => $currentStock
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Friday stock check',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload stock photos
     */
    public function uploadStock(Request $request)
    {
        try {
            $user = $request->user();
            $agent = DeliveryAgent::where('user_id', $user->id)->first();
            
            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery agent profile not found'
                ], 404);
            }

            $request->validate([
                'stock_photos' => 'required|array',
                'stock_photos.*' => 'required|string',
                'stock_levels' => 'required|array'
            ]);

            $stockPhotos = $request->input('stock_photos');
            $stockLevels = $request->input('stock_levels');

            // Save stock photos
            foreach ($stockPhotos as $index => $photo) {
                DB::table('stock_photos')->insert([
                    'delivery_agent_id' => $agent->id,
                    'photo_data' => $photo,
                    'stock_levels' => json_encode($stockLevels),
                    'uploaded_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }

            // Update agent's bin with current stock levels
            $agentBin = Bin::where('delivery_agent_id', $agent->id)->first();
            if ($agentBin) {
                $agentBin->update([
                    'shampoo_count' => $stockLevels['shampoo'] ?? 0,
                    'pomade_count' => $stockLevels['pomade'] ?? 0,
                    'conditioner_count' => $stockLevels['conditioner'] ?? 0
                ]);
            }

            // Calculate next deadline
            $nextFriday = Carbon::now()->next(Carbon::FRIDAY)->setTime(12, 0, 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'uploaded',
                    'photos_count' => count($stockPhotos),
                    'next_deadline' => $nextFriday->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload stock photos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
