<?php

namespace App\Actions\Crm;

use App\Models\Crm\Lead;
use App\Models\Crm\PipelineStage;
use Illuminate\Validation\ValidationException;

class MoveLeadStageAction
{
    public function execute(Lead $lead, int $pipelineStageId, ?string $reason = null): Lead
    {
        $stage = PipelineStage::where('id', $pipelineStageId)
            ->where('pipeline_id', $lead->pipeline_id)
            ->first();

        if (! $stage) {
            throw ValidationException::withMessages([
                'pipeline_stage_id' => ['The specified stage does not belong to the lead\'s current pipeline.'],
            ]);
        }

        $lead->pipeline_stage_id = $stage->id;
        $lead->probability = $stage->probability;

        if ($stage->is_won_stage) {
            $lead->won_at = now();
            $lead->lost_at = null;
        } elseif ($stage->is_lost_stage) {
            $lead->lost_at = now();
            $lead->won_at = null;
        }

        $lead->save();

        return $lead;
    }
}
