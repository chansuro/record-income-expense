<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateConversationRequest;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIChatController extends Controller
{
    protected OpenAIService $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Create a new conversation
     */
    public function createConversation(CreateConversationRequest $request): JsonResponse
    {
        $conversation = Conversation::create([
            'user_id' => $request->user_id,
            'title' => $request->title,
            'model' => $request->model ?? config('services.openai.model'),
            'system_prompt' => config('services.openai.system_prompt'),
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation created successfully.',
            'data' => $conversation,
            'suggestions' => [
                    ["title"=>"Can I claim fuel?","icon"=>"https://storage.googleapis.com/taxitax/icons/fuel.png"],
                    ["title"=>"Mileage vs actual costs","icon"=>"https://storage.googleapis.com/taxitax/icons/vehicle_finance_cost.png"],
                    ["title"=>"What expenses can I claim?","icon"=>"https://storage.googleapis.com/taxitax/icons/insurance.png"],
                    ["title"=>"Do I need VAT registration?","icon"=>"https://storage.googleapis.com/taxitax/icons/road_tax.png"]
            ],
        ], 201);
    }

    /**
     * List all conversations
     */
    public function conversations($user_id): JsonResponse
    {
        $conversations = Conversation::where('user_id', $user_id)
            ->latest('last_message_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conversations,
            'suggestions' => [
                    ["title"=>"Can I claim fuel?","icon"=>"https://storage.googleapis.com/taxitax/icons/fuel.png"],
                    ["title"=>"Mileage vs actual costs","icon"=>"https://storage.googleapis.com/taxitax/icons/vehicle_finance_cost.png"],
                    ["title"=>"What expenses can I claim?","icon"=>"https://storage.googleapis.com/taxitax/icons/insurance.png"],
                    ["title"=>"Do I need VAT registration?","icon"=>"https://storage.googleapis.com/taxitax/icons/road_tax.png"]
            ]
        ]);
    }

    /**
     * Conversation history
     */
    public function history(Conversation $conversation,$user_id): JsonResponse
    {
        abort_if($conversation->user_id != $user_id, 403);

        $messages = $conversation->messages()
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'messages' => $messages
        ]);
    }

    /**
     * Send message
     */
    public function send(SendMessageRequest $request): JsonResponse
    {
        $conversation = Conversation::findOrFail(
                $request->conversation_id
        );
    
        abort_if($conversation->user_id != $request->user_id, 403);

        $history = $conversation->messages()
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            })
            ->toArray();

        $classification = $this->openAIService->classifyMessage($request->message, $history);
        if ($classification !== 'uk_tax') {
            $assistantData = [
                'decision' => 'reject',
                'on_topic' => false,
                'answer' =>
                    'I can only assist with UK tax and HMRC-related questions.',
                // 'reason' =>
                //     'Your request is outside the UK tax consultancy scope.',
                // 'follow_up_question' =>
                //     'What UK tax or HMRC question can I help you with?',
                // 'suggestions' => [
                //         ["title"=>"Can I claim fuel?","icon"=>"fuel"],
                //         ["title"=>"Mileage vs actual costs","icon"=>"car"],
                //         ["title"=>"What expenses can I claim?",
                //         "icon"=>"receipt"],
                //         ["title"=>"Do I need VAT registration?",
                //         "icon"=>"vat"]
                // ],
            ];
        
            $assistantMessage = [
                'role' => 'assistant',
                'content' => $assistantData['answer'],
                'structured_data' => $assistantData,
            ];
        
            return response()->json([
                'success' => true,
                'message' => $assistantMessage,
                'data' => $assistantData,
            ]);
        }else{
            // Save user message
            Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $request->message
            ]);
    
            // Auto title from first message
            if (empty($conversation->title)) {
                $conversation->update([
                    'title' => substr($request->message, 0, 50)
                ]);
            }
    
            // Generate AI reply
            $assistantMessage = $this->openAIService
                ->generateResponse($conversation);
    
            return response()->json([
                'success' => true,
                'reply' => $assistantMessage->content,
                'message' => $assistantMessage
            ]);
        }
        
    }

    /**
     * Delete conversation
     */
    public function destroy(Conversation $conversation,$user_id): JsonResponse
    {
        abort_if($conversation->user_id != $user_id, 403);

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted.'
        ]);
    }
}
