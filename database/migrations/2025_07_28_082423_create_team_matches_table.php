<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_matches', function (Blueprint $table) {
            $table->id();
            
            // Match Information
            $table->string('match_id', 20)->unique(); // TMM001, TMM002, etc.
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('job_posting_id')->constrained('job_postings')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            
            // Team Analysis
            $table->json('team_composition')->nullable(); // Current team members
            $table->json('team_culture')->nullable(); // Team culture assessment
            $table->json('team_dynamics')->nullable(); // Team dynamics analysis
            $table->json('team_gaps')->nullable(); // Skills gaps in team
            
            // Match Scores
            $table->decimal('overall_match_score', 3, 2)->nullable(); // 0.00 to 10.00
            $table->decimal('skill_match_score', 3, 2)->nullable();
            $table->decimal('personality_match_score', 3, 2)->nullable();
            $table->decimal('work_style_match_score', 3, 2)->nullable();
            $table->decimal('communication_match_score', 3, 2)->nullable();
            $table->decimal('leadership_match_score', 3, 2)->nullable();
            
            // Detailed Analysis
            $table->json('skill_complementarity')->nullable(); // How skills complement team
            $table->json('personality_fit')->nullable(); // Personality fit with team
            $table->json('work_style_compatibility')->nullable(); // Work style compatibility
            $table->json('communication_style_fit')->nullable(); // Communication style fit
            $table->json('leadership_potential')->nullable(); // Leadership potential in team
            $table->json('collaboration_potential')->nullable(); // Collaboration potential
            
            // Team Impact Prediction
            $table->json('team_impact_prediction')->nullable(); // Predicted impact on team
            $table->json('team_synergy_prediction')->nullable(); // Predicted team synergy
            $table->json('potential_conflicts')->nullable(); // Potential conflicts identified
            $table->json('team_growth_potential')->nullable(); // Team growth potential
            
            // Cultural Fit
            $table->json('company_culture_fit')->nullable(); // Fit with company culture
            $table->json('department_culture_fit')->nullable(); // Fit with department culture
            $table->json('team_culture_fit')->nullable(); // Fit with specific team culture
            $table->decimal('cultural_fit_score', 3, 2)->nullable();
            
            // Diversity and Inclusion
            $table->json('diversity_impact')->nullable(); // Impact on team diversity
            $table->json('inclusion_potential')->nullable(); // Inclusion potential
            $table->decimal('diversity_score', 3, 2)->nullable();
            
            // Risk Assessment
            $table->json('team_risk_factors')->nullable(); // Risk factors for team
            $table->json('mitigation_strategies')->nullable(); // Risk mitigation strategies
            $table->decimal('risk_score', 3, 2)->nullable();
            
            // Recommendations
            $table->json('team_recommendations')->nullable(); // Recommendations for team
            $table->json('onboarding_suggestions')->nullable(); // Onboarding suggestions
            $table->json('mentorship_recommendations')->nullable(); // Mentorship recommendations
            $table->text('overall_recommendation')->nullable();
            
            // Assessment Metadata
            $table->enum('status', ['pending', 'in_progress', 'completed', 'reviewed'])->default('pending');
            $table->timestamp('assessed_at')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Human Review
            $table->boolean('human_reviewed')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('human_feedback')->nullable();
            $table->boolean('human_agrees_with_ai')->nullable();
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['match_id', 'status']);
            $table->index(['candidate_id', 'status']);
            $table->index(['job_posting_id', 'status']);
            $table->index('overall_match_score');
            $table->index('cultural_fit_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_matches');
    }
};
