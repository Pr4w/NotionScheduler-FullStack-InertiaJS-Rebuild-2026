<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\NotionDatabases;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use Carbon\Carbon;

use App\Models\User;

class NotionHttp extends Model
{

    public array $context;
    public string $token;
    public string $page_id;

    public array $patch_data;

    public string $column_comments;
    public string $column_status;
    public string $column_checkbox;

    public array $scaffolding;

    public $whatdo;
    public $errors;

    public $userid;

    public function __construct(
        // Basics
        $context,
        $token,
        $page_id,

        // Columns
        $column_comments = '',
        $column_status = '',
        $column_checkbox = '',

    ) {

        $this->context = $context;
        $this->token = $token;
        $this->page_id = $page_id;

        $this->column_comments = $column_comments;
        $this->column_status = $column_status;
        $this->column_checkbox = $column_checkbox;

        $this->scaffolding = NotionDatabases::getDefaultScaffolding();

        $this->userid = $this->context['userid'] ?? null;

    }

    
    public function patchPage() {

        // Log stuff
        Log::withContext($this->context);

        // Print in log 
        try {
            if ($this->whatdo == 'success') {
                Log::info("Marking the post as successful in Notion");
            } elseif ($this->whatdo == 'errors') {
                Log::info("A user (" . $this->userid . ") tried to schedule a post but ran into errors...");
                Log::info($this->errors);
            } elseif ($this->whatdo == 'scheduled') {
                Log::info("Adding a post to the scheduler for user (" . $this->userid . ")...");
            } elseif ($this->whatdo == 'reset') {
                Log::info("Reseting a post to initial status");
            } else {
                Log::warning("Unknown 'whatdo'");
                Log::info($this->whatdo);
            }
        } catch (\Exception $e) {
            Log::info(76);
            Log::info($e);
        }

        // Make the request
        $response = Http::withToken($this->token)
            ->withHeaders([
                'Notion-Version' => '2022-06-28'
            ])
            ->patch('https://api.notion.com/v1/pages/' . $this->page_id, [
                    'properties' => $this->patch_data
                ]
            );

        
        // Make pretty
        $rep = $response->json();

        // Check if the request is successfull
        if (!$response->successful()) {


            if (isset($rep['message'])) {
                throw new \Exception($rep['message']);
            }

            Log::info("NotionHttp error 32");
            Log::info($rep);
            Log::info($response);

            return false;

        } else {

            return $rep;

        }

    }

    public function markPost($errors, $status, $checkbox) {
        $this->patch_data = [
            $this->column_comments => [
                'rich_text' => [
                    [
                        "type" => "text",
                        'text' => [
                            'content' =>  $errors,
                        ]
                    ]
                    
                ]
            ],
            $this->column_status => [
                'select' => $status
            ],
            $this->column_checkbox => [
                'checkbox' => $checkbox
            ]
        ];

        return $this->patchPage();
    }

    // NOTE - Success
    public function markPostAsSuccessful($message) {
        $this->whatdo = 'success';

        // Check
        if (is_null($message)) {
            $message = '';
        }

        return $this->markPost(
            $message,
            $this->setStatusSelect('posted'),
            false
        );
    }

    // NOTE - Error
    public function markPostAsError($errors) {
        $this->whatdo = 'errors';

        // Checks and cleanups
        if (is_array($errors)) {
            $errors = implode(" - ", $errors);
        }
        $errors =  "⚠️ - " . $errors;
        $this->errors = $errors;

        return $this->markPost(
            $errors,
            $this->setStatusSelect('error'),
            false
        );
    }
    
    // NOTE - Scheduled
    public function markPostAsScheduled() {
        $this->whatdo = 'scheduled';

        return $this->markPost(
            '',
            $this->setStatusSelect('scheduled'),
            false
        );
    }

    // NOTE - Helper for formatting
    public function setStatusSelect($key) {
        return [
            'name' => $this->scaffolding['properties']['notion_status']['sub_options'][$key]['name']
        ];
    }

    // NOTE - Reset the post
    public function resetPostToDefault() {
        $this->whatdo = 'reset';

        return $this->markPost(
            '',
            null,
            false
        );
    }


    
}