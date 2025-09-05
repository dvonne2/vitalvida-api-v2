<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function getInventoryGuide()
    {
        // Mock knowledge base data for inventory management
        $knowledgeData = [
            [
                'id' => 'KB-001',
                'topic' => 'Low Stock Threshold',
                'category' => 'stock_management',
                'details' => 'Bins must never fall below 15% stock. Refill at 20% to maintain buffer.',
                'severity' => 'critical',
                'related_penalties' => 'Stock-out penalties: ₦5,000 per day',
                'best_practice' => 'Check stock levels every morning and evening'
            ],
            [
                'id' => 'KB-002',
                'topic' => 'Photo Verification Timing',
                'category' => 'documentation',
                'details' => 'Photos must be submitted by 7 PM daily to avoid fines.',
                'severity' => 'high',
                'related_penalties' => 'Late submission: ₦2,000 fine',
                'best_practice' => 'Take photos during inventory count, not after'
            ],
            [
                'id' => 'KB-003',
                'topic' => 'Return Protocol',
                'category' => 'returns',
                'details' => 'Returned items must match DA logs + Zoho record. Mismatch = strike.',
                'severity' => 'critical',
                'related_penalties' => 'False return claim: 3 penalty points + ₦10,000 fine',
                'best_practice' => 'Double-check serial numbers and quantities before logging returns'
            ],
            [
                'id' => 'KB-004',
                'topic' => 'Tamper-Proofing',
                'category' => 'security',
                'details' => 'All movement must be logged. Manual edits will be flagged.',
                'severity' => 'critical',
                'related_penalties' => 'Unauthorized changes: Immediate investigation',
                'best_practice' => 'Use system tools only. Contact IT for any corrections needed'
            ],
            [
                'id' => 'KB-005',
                'topic' => 'Daily Reconciliation',
                'category' => 'procedures',
                'details' => 'Physical count must match system records within 2% variance.',
                'severity' => 'high',
                'related_penalties' => 'Variance >5%: ₦3,000 fine per occurrence',
                'best_practice' => 'Count twice, log once. Use barcode scanner when available'
            ],
            [
                'id' => 'KB-006',
                'topic' => 'Customer Complaint Response',
                'category' => 'customer_service',
                'details' => 'All complaints must be logged within 2 hours of receipt.',
                'severity' => 'medium',
                'related_penalties' => 'Delayed response: ₦1,500 fine',
                'best_practice' => 'Acknowledge immediately, investigate within 24 hours'
            ],
            [
                'id' => 'KB-007',
                'topic' => 'Expired Product Handling',
                'category' => 'quality_control',
                'details' => 'Products within 30 days of expiry must be flagged for clearance.',
                'severity' => 'high',
                'related_penalties' => 'Selling expired products: ₦15,000 fine + 5 penalty points',
                'best_practice' => 'Weekly expiry date audits. FIFO rotation mandatory'
            ],
            [
                'id' => 'KB-008',
                'topic' => 'Emergency Stock Requests',
                'category' => 'procedures',
                'details' => 'Emergency requests require manager approval and must include justification.',
                'severity' => 'medium',
                'related_penalties' => 'Unauthorized emergency orders: ₦2,500 fine',
                'best_practice' => 'Plan ahead. Emergency should be genuine urgent customer need'
            ]
        ];

        // Summary statistics
        $summary = [
            'total_topics' => count($knowledgeData),
            'critical_policies' => count(array_filter($knowledgeData, fn($item) => $item['severity'] === 'critical')),
            'high_priority' => count(array_filter($knowledgeData, fn($item) => $item['severity'] === 'high')),
            'last_updated' => '2025-07-09T10:30:00Z'
        ];

        // Common search queries (for future enhancement)
        $frequently_searched = [
            'Why was I fined?',
            'How to avoid stock discrepancies?',
            'Photo submission requirements',
            'Return process steps',
            'Emergency procedures'
        ];

        return response()->json([
            'status' => 'success',
            'data' => $knowledgeData,
            'summary' => $summary,
            'frequently_searched' => $frequently_searched
        ]);
    }
}
