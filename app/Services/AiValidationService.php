<?php

namespace App\Services;

use App\Models\DeliveryAgent;
use App\Models\AgentRequirement;
use App\Models\AiValidation;
use App\Models\SystemActivity;

class AiValidationService
{
    public function validateRequirements(DeliveryAgent $agent, AgentRequirement $requirements)
    {
        $score = $requirements->getRequirementScore();
        $confidence = rand(85, 95);
        
        $validationResult = [
            'requirement_score' => $score,
            'smartphone_check' => $requirements->has_smartphone,
            'transportation_check' => $requirements->has_transportation,
            'storage_check' => $requirements->can_store_products,
            'portal_comfort_check' => $requirements->comfortable_with_portal
        ];

        AiValidation::create([
            'agent_id' => $agent->id,
            'validation_type' => 'data',
            'ai_score' => $score,
            'confidence_level' => $confidence,
            'validation_result' => $validationResult,
            'passed' => $score >= 80
        ]);

        return $score;
    }

    public function runOverallValidation(DeliveryAgent $agent)
    {
        $documentValidations = $agent->aiValidations()
                                   ->where('validation_type', 'document')
                                   ->where('passed', true)
                                   ->get();

        $dataValidations = $agent->aiValidations()
                                ->where('validation_type', 'data')
                                ->where('passed', true)
                                ->get();

        $documentScore = $documentValidations->avg('ai_score') ?: 0;
        $dataScore = $dataValidations->avg('ai_score') ?: 0;
        
        // Overall score calculation (weighted)
        $overallScore = ($documentScore * 0.6) + ($dataScore * 0.4);
        
        $validationResult = [
            'document_score' => round($documentScore, 2),
            'data_score' => round($dataScore, 2),
            'overall_calculation' => [
                'document_weight' => 60,
                'data_weight' => 40,
                'final_score' => round($overallScore, 2)
            ],
            'recommendation' => $this->getRecommendation($overallScore)
        ];

        $validation = AiValidation::create([
            'agent_id' => $agent->id,
            'validation_type' => 'overall',
            'ai_score' => $overallScore,
            'confidence_level' => rand(90, 98),
            'validation_result' => $validationResult,
            'passed' => $overallScore >= 75
        ]);

        // Update agent AI score
        $agent->update(['ai_score' => $overallScore]);

        return $overallScore;
    }

    private function getRecommendation($score)
    {
        return match(true) {
            $score >= 90 => 'STRONGLY_RECOMMEND_APPROVAL',
            $score >= 80 => 'RECOMMEND_APPROVAL',
            $score >= 70 => 'CONDITIONAL_APPROVAL',
            $score >= 60 => 'MANUAL_REVIEW_REQUIRED',
            default => 'RECOMMEND_REJECTION'
        };
    }
} 