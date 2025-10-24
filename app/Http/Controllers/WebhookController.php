<?php

namespace App\Http\Controllers;

use App\Jobs\StoreDgveryLive;
use App\Jobs\StoreDgveryTrackingError;
use App\Jobs\UpdateDgveryLive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {

        Log::info('webhook received', $request->all());

        if ($request->header('X-NinjaRMM-Event') !== null) {
            $this->ninjaRmm($request->all());

            return response()->json(['success' => true]);
        }

        // Process webhook payload
        switch ($request->header('X-Support-Webhook-Event')) {
            case 'ticket.new_live_academelearning':
                // Handle ticket created event
                dispatch(new StoreDgveryLive($request->all()));

                break;
            case 'ticket.update_live_academelearning':

                // Handle ticket udpated event
                dispatch(new UpdateDgveryLive($request->all()));

                break;
            case 'ticket.new_tracking_error_academelearning':
                // Handle ticket created event
                dispatch(new StoreDgveryTrackingError($request->all()));

                break;
            default:
                // Handle other events
                $message = 'No...';
                break;
        }

        // Perform actions based on the webhook data

        return response()->json(['success' => true]);
    }

    private function ninjaRmm($data)
    {
        // Process NinjaRMM webhook payload

        Log::info('NinjaRMM webhook received', $data);
    }
}
