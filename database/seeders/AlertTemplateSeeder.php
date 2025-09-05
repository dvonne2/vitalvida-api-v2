<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AlertTemplate;

class AlertTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AlertTemplate::create([
            'name' => 'Critical Fraud Alert',
            'type' => 'fraud_alert',
            'sms_template' => '🚨 FRAUD: {staff}-{amount} | FROZEN | vitalvida.com/gm',
            'whatsapp_template' => "🚨 *CRITICAL FRAUD DETECTED*\n\nStaff: {staffName}\nIssue: {fraudType}\nAmount: {amount}\nTime: {timestamp}\n\n*AUTO-ACTIONS:*\n{autoActions}\n\n*GM ACTIONS:*\n1️⃣ Call staff\n2️⃣ Escalate to COO\n3️⃣ View details\n4️⃣ False alarm",
            'recipients' => ['GM_PRIMARY', 'COO_BACKUP'],
            'priority' => 'critical',
        ]);

        AlertTemplate::create([
            'name' => 'Stock Emergency',
            'type' => 'stock_emergency',
            'sms_template' => '📦 STOCK: {location} {product} runs out in {days} days | Risk: {amount}',
            'whatsapp_template' => "📦 *STOCK EMERGENCY*\n\nLocation: {location}\nProduct: {product}\nDays left: {days}\nRevenue at risk: {amount}\n\nTake action now!",
            'recipients' => ['GM_PRIMARY', 'SUPPLY_TEAM'],
            'priority' => 'high',
        ]);

        AlertTemplate::create([
            'name' => 'DA Performance Issue',
            'type' => 'da_performance',
            'sms_template' => '⚠️ DA: {daName} no movement {dayCount} days | Stock: {stockValue}',
            'whatsapp_template' => "⚠️ *DA PERFORMANCE ISSUE*\n\nDA: {daName}\nIssue: {issue}\nDays: {dayCount}\nStock value: {stockValue}\n\n*ACTIONS:*\nCALL - Phone DA\nBLOCK - Stop restocks\nMOVE - Redistribute stock",
            'recipients' => ['GM_PRIMARY'],
            'priority' => 'medium',
        ]);

        AlertTemplate::create([
            'name' => 'Payment Mismatch Alert',
            'type' => 'payment_mismatch',
            'sms_template' => '💰 PAYMENT: {staff} marked {orders} paid - {amount} | Moniepoint: ₦0 | vitalvida.com/gm',
            'whatsapp_template' => "💰 *PAYMENT MISMATCH*\n\nStaff: {staffName}\nOrders: {orders}\nAmount: {amount}\nMoniepoint: ₦0 received\n\n*ACTIONS:*\n1️⃣ Call staff\n2️⃣ Verify payments\n3️⃣ Block payouts\n4️⃣ Investigate",
            'recipients' => ['GM_PRIMARY', 'FINANCE_MANAGER'],
            'priority' => 'critical',
        ]);

        $this->command->info('Alert templates seeded successfully!');
    }
}
