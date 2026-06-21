<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\WhatsAppAccount;
use App\Services\ApiResponseService;
use App\Services\Crm\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly WhatsAppWebhookService $whatsAppWebhookService,
    ) {
        parent::__construct($api);
    }

    public function verify(Request $request, WhatsAppAccount $whatsAppAccount): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $result = $this->whatsAppWebhookService->verifyChallenge(
            $mode,
            $token,
            $challenge,
            $whatsAppAccount
        );

        if ($result !== null) {
            return response($result, 200)->header('Content-Type', 'text/plain');
        }

        return $this->api->error('Verification failed', 403);
    }

    public function handle(Request $request, WhatsAppAccount $whatsAppAccount): JsonResponse
    {
        $this->whatsAppWebhookService->processPayload(
            $request->all(),
            $whatsAppAccount
        );

        return $this->api->success('Webhook processed successfully');
    }
}
