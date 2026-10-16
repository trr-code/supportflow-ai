<?php

namespace App\Ai\Tools;

use App\Models\ReplacementProposal;
use App\Models\ReplacementRequest;
use App\Services\ReplacementCaseService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateReplacementRequest implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        public string $caseId,
        public int $generation,
    ) {}

    public function description(): Stringable|string
    {
        return 'Prepare a fictional replacement for a damaged item. Call this in the same turn once the visitor has asked for a replacement and the order, damage policy, and Kent stock say one can be prepared. Do not ask again first. The visitor confirms before anything is saved.';
    }

    public function handle(Request $request): Stringable|string
    {
        $proposal = ReplacementProposal::query()
            ->where('tool_call_id', (string) $request->toolCallId())
            ->first();

        if (! $proposal instanceof ReplacementProposal) {
            return 'Nothing was saved.';
        }

        $saved = app(ReplacementCaseService::class)->commit(
            $proposal->id,
            $this->generation,
            $request->all(),
        );

        if (! $saved instanceof ReplacementRequest) {
            return 'Nothing was saved.';
        }

        return 'Your replacement request is saved. Reference '.$saved->reference.'. Nothing is shipped.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->required(),
            'quantity' => $schema->integer()->min(1)->max(1)->required(),
            'reason' => $schema->string()->required(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('This will save a replacement request for you to confirm.');
    }
}
