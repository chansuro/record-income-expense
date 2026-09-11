<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HmrcClientAuthorisation;
use App\Services\HmrcService;
use Illuminate\Http\Request;

class HmrcClientAuthorisationController extends Controller
{
    public function store(Request $request,HmrcService $hmrc) {
        $validated = $request->validate([
            'nino' => [
                'required',
                'string',
            ],

            'postcode' => [
                'required',
                'string',
                'max:10',
            ],
            'vehicle_exp' => [
                'nullable',
                'string',
            ],
        ]);

        try {
            $invitation = $hmrc->createClientInvitation(
                $validated['nino'],
                $validated['postcode'],
                'main'
            );
            $authorisation =
                HmrcClientAuthorisation::create([
                    'user_id' =>auth()->id(),
                    'environment' =>config('services.hmrc.environment'),
                    'service' =>'MTD-IT',
                    'client_type' =>'personal',
                    'client_id_type' =>'ni',
                    'client_id' =>
                        strtoupper(
                            str_replace(
                                ' ',
                                '',
                                $validated['nino']
                            )
                        ),
                    'nino'=>$validated['nino'],  
                    'post_code'=>$validated['postcode'],
                    'invitation_id' =>
                        $invitation['invitation_id'],
                    'client_action_url' =>
                        $invitation['client_action_url'],
                    'agent_type' =>
                        'main',
                    'vehicle_exp' =>
                        $validated['vehicle_exp'] ?? null,
                    'status' =>
                        $invitation['status'],
                    'expires_at' =>
                        $invitation['expires_on'],
                ]);
            $environment = config('services.hmrc.environment');
            return response()->json([
                'success' => true,
                'message' =>'HMRC authorisation request created successfully.',
                'data' => [
                    'invitation_id' => $authorisation->invitation_id,
                    'status' => $authorisation->status,
                    'environment' => $environment,
                    'authorisation_mode' => $environment === 'sandbox' ? 'sandbox_accept' : 'browser',
                    'authorisation_url' => $environment === 'production' ? $authorisation->client_action_url : null,
                    'expires_at' => $authorisation->expires_at,
                ],
            ]);

        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' =>
                    $e->getMessage(),
            ], 422);
        }
    }
    public function acceptSandbox(string $invitationId,HmrcService $hmrc) {
        /*
        |--------------------------------------------------------------------------
        | Never allow this endpoint in production
        |--------------------------------------------------------------------------
        */
        if (config('services.hmrc.environment') !== 'sandbox') {

            return response()->json([
                'success' => false,
                'message' =>
                    'Sandbox HMRC authorisation acceptance is not available in production.',
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Find invitation belonging to logged-in AppTax customer
        |--------------------------------------------------------------------------
        */
        $authorisation = HmrcClientAuthorisation::where('user_id',auth()->id())->where('environment','sandbox')->where('invitation_id',$invitationId)->first();
        if (!$authorisation) {
            return response()->json([
                'success' => false,
                'message' =>
                    'HMRC authorisation request not found.',
            ], 404);
        }


        /*
        |--------------------------------------------------------------------------
        | We only expect a Pending invitation here
        |--------------------------------------------------------------------------
        */
        if ($authorisation->status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' =>
                    'This HMRC authorisation request is already '
                    . $authorisation->status . '.',
            ], 409);
        }


        try {
            /*
            |--------------------------------------------------------------------------
            | Tell HMRC sandbox to accept invitation
            |--------------------------------------------------------------------------
            */
            $invitation = $hmrc->acceptSandboxInvitation($authorisation->invitation_id);

            /*
            |--------------------------------------------------------------------------
            | Use HMRC response as source of truth
            |--------------------------------------------------------------------------
            */
            $status = $invitation['status'] ?? null;
            if (!$status) {
                throw new \RuntimeException(
                    'HMRC did not return the authorisation status.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Update local database
            |--------------------------------------------------------------------------
            */
            $update = [
                'status' => $status,
                'last_checked_at' => now(),
            ];

            if ($status === 'Accepted') {
                $update['accepted_at'] = now();
            }
            $authorisation->update($update);
            /*
            |--------------------------------------------------------------------------
            | Return updated status
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' =>
                    'HMRC sandbox authorisation accepted successfully.',
                'data' => [
                    'invitation_id' =>
                        $authorisation->invitation_id,
                    'status' =>
                        $authorisation->fresh()->status,
                    'accepted_at' =>
                        $authorisation->fresh()->accepted_at,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function status(HmrcService $hmrc) {
        try {
            /*
            |--------------------------------------------------------------------------
            | Find invitation belonging to logged-in AppTax customer
            |--------------------------------------------------------------------------
            */
            $authorisation = HmrcClientAuthorisation::where(
                'user_id',
                auth()->id()
            )
            ->where(
                'environment',
                config('services.hmrc.environment')
            )
            ->first();

            if (!$authorisation) {

                return response()->json([
                    'success' => false,
                    'message' => 'HMRC authorisation request not found.',
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | Ask HMRC for latest status
            |--------------------------------------------------------------------------
            */
            $invitation = $hmrc->getInvitation(
                $authorisation->invitation_id
            );

            $status = $invitation['status'] ?? null;

            if (!$status) {

                return response()->json([
                    'success' => false,
                    'message' => 'HMRC did not return an authorisation status.',
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Update local database
            |--------------------------------------------------------------------------
            */
            $updateData = [
                'status' => $status,
                'last_checked_at' => now(),
            ];

            if (
                $status === 'Accepted' &&
                !$authorisation->accepted_at
            ) {
                $updateData['accepted_at'] = now();
            }

            $authorisation->update($updateData);

            $authorisation->refresh();


            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => $status === 'Accepted' ? 'HMRC authorisation completed successfully.' : 'HMRC authorisation is still ' . strtolower($status) . '.',
                'data' => [
                    'id' => $authorisation->id,
                    'invitation_id' => $authorisation->invitation_id,
                    'status' => $authorisation->status,
                    'nino' => $authorisation->nino,
                    'post_code' => $authorisation->post_code,
                    'type_of_business' => $authorisation->typeOfBusiness,
                    'business_id' => $authorisation->businessId,
                    'trading_type' => $authorisation->tradingType,
                    'trading_name' => $authorisation->tradingName,
                    'accepted' => $authorisation->status === 'Accepted',
                    'accepted_at' => $authorisation->accepted_at,
                    'expires_at' => $authorisation->expires_at,
                ],
            ]);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    


}